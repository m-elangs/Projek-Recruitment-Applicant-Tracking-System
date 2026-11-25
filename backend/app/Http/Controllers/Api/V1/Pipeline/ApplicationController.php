<?php

namespace App\Http\Controllers\Api\V1\Pipeline;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\SubmitApplicationRequest;
use App\Http\Requests\Recruitment\TransitionStageRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\Candidate;
use App\Models\Job;
use App\Services\ApplicationPipelineService;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationPipelineService $pipelineService,
        private readonly AuditLogService $auditLog
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->pipelineService->paginate($request->all(), $request->user());

        return ApiResponse::paginated($paginator, ApplicationResource::class, 'Applications retrieved.');
    }

    public function pipeline(Request $request): JsonResponse
    {
        $jobId = $request->query('job_id') ? (int) $request->query('job_id') : null;
        $board = $this->pipelineService->getKanbanBoard($jobId, $request->user());

        return ApiResponse::success($board, 'Recruitment pipeline board retrieved.');
    }

    public function store(SubmitApplicationRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $job = Job::query()->findOrFail($validated['job_id']);

        if ($user->isCandidate()) {
            $candidate = $user->candidate;
            if (! $candidate) {
                throw new BusinessRuleException('CANDIDATE_PROFILE_MISSING', 'Candidate profile was not found for this account.', 422);
            }
        } else {
            if (empty($validated['candidate_id'])) {
                throw new BusinessRuleException('CANDIDATE_ID_REQUIRED', 'candidate_id is required when submitting an application on behalf of a candidate.', 422);
            }
            $candidate = Candidate::query()->findOrFail($validated['candidate_id']);
        }

        $application = $this->pipelineService->submit($job, $candidate, $validated, $user);

        return ApiResponse::created(new ApplicationResource($application), 'Application submitted successfully.');
    }

    public function show(Application $application): JsonResponse
    {
        $application->loadMissing(['job', 'candidate']);
        $this->authorize('view', $application);

        $application->load([
            'job.department',
            'candidate.skills',
            'candidate.experiences',
            'candidate.educations',
            'currentStage',
            'cvDocument',
            'histories.fromStage',
            'histories.toStage',
            'histories.actor:id,name,email',
            'interviews.panelUsers:id,name,job_title',
            'interviews.evaluations.interviewer:id,name,job_title',
            'offers.department:id,code,name',
            'notes.author:id,name,job_title',
        ]);

        return ApiResponse::success(new ApplicationResource($application), 'Application details retrieved.');
    }

    public function transitionStage(TransitionStageRequest $request, Application $application): JsonResponse
    {
        $this->authorize('transition', $application);
        $validated = $request->validated();

        $updated = $this->pipelineService->transitionStage(
            application: $application,
            targetStageSlug: $validated['stage_slug'],
            actor: $request->user(),
            reason: $validated['reason'] ?? null,
            notes: $validated['notes'] ?? null
        );

        return ApiResponse::success(new ApplicationResource($updated), 'Application stage updated successfully.');
    }

    public function recommend(Request $request, Application $application): JsonResponse
    {
        $application->loadMissing('job');
        $this->authorize('recommend', $application);

        $validated = $request->validate([
            'recommendation' => ['required', Rule::in(['pending', 'strong_yes', 'yes', 'neutral', 'no', 'strong_no'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->pipelineService->updateHiringManagerRecommendation(
            $application,
            $validated['recommendation'],
            $validated['notes'] ?? null,
            $request->user()
        );

        return ApiResponse::success(new ApplicationResource($updated), 'Hiring manager recommendation saved.');
    }

    public function notes(Application $application): JsonResponse
    {
        $application->loadMissing('job');
        $this->authorize('note', $application);

        $notes = $application->notes()->with('author:id,name,job_title')->get();

        return ApiResponse::success($notes, 'Application notes retrieved.');
    }

    public function storeNote(Request $request, Application $application): JsonResponse
    {
        $application->loadMissing('job');
        $this->authorize('note', $application);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ]);

        $note = $application->notes()->create([
            'author_user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_internal' => true,
        ]);

        $this->auditLog->log(
            eventType: 'application.note_added',
            auditable: $application,
            description: "Internal note added to application {$application->application_number}",
            actor: $request->user()
        );

        return ApiResponse::created($note->load('author:id,name,job_title'), 'Internal note added.');
    }

    public function destroyNote(Request $request, Application $application, ApplicationNote $note): JsonResponse
    {
        if ($note->application_id !== $application->id) {
            abort(404);
        }

        $user = $request->user();
        if (! $user->isAdmin() && $note->author_user_id !== $user->id) {
            abort(403, 'You can only delete notes you authored.');
        }

        $note->delete();

        return ApiResponse::success(null, 'Note deleted.');
    }
}
