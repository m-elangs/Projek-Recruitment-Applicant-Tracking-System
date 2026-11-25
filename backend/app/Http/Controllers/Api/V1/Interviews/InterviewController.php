<?php

namespace App\Http\Controllers\Api\V1\Interviews;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\InterviewEvaluation;
use App\Services\InterviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InterviewController extends Controller
{
    public function __construct(
        private readonly InterviewService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->paginate($request->all(), $request->user());

        if ($request->user()->isCandidate()) {
            $paginator->getCollection()->transform(function (Interview $iv) {
                $iv->makeHidden(['internal_agenda']);
                $iv->unsetRelation('evaluations');

                return $iv;
            });
        }

        return ApiResponse::paginated($paginator, null, 'Interviews retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage', Interview::class);

        $validated = $request->validate([
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'title' => ['required', 'string', 'max:180'],
            'round_number' => ['nullable', 'integer', 'min:1', 'max:10'],
            'interview_type' => ['nullable', Rule::in(['screening', 'technical', 'behavioral', 'panel', 'assessment_review', 'final'])],
            'mode' => ['required', Rule::in(['online', 'onsite'])],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'meeting_url' => ['required_if:mode,online', 'nullable', 'url', 'max:500'],
            'location' => ['required_if:mode,onsite', 'nullable', 'string', 'max:255'],
            'candidate_instructions' => ['nullable', 'string', 'max:2000'],
            'internal_agenda' => ['nullable', 'string', 'max:3000'],
            'interviewer_ids' => ['required', 'array', 'min:1'],
            'interviewer_ids.*' => ['required', 'integer', 'exists:users,id'],
            'lead_interviewer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $interview = $this->service->schedule($validated, $request->user());

        return ApiResponse::created($interview, 'Interview scheduled successfully.');
    }

    public function show(Request $request, Interview $interview): JsonResponse
    {
        $interview->loadMissing(['application.job', 'application.candidate']);
        $this->authorize('view', $interview);

        $relations = [
            'application.job.department',
            'application.candidate:id,first_name,last_name,email,phone,current_title',
            'application.currentStage',
            'panelUsers:id,name,email,job_title',
            'scheduler:id,name,email',
        ];

        if (! $request->user()->isCandidate()) {
            $relations[] = 'evaluations.interviewer:id,name,email,job_title';
        }

        $interview->load($relations);

        if ($request->user()->isCandidate()) {
            $interview->makeHidden(['internal_agenda']);
        }

        return ApiResponse::success($interview, 'Interview details retrieved.');
    }

    public function update(Request $request, Interview $interview): JsonResponse
    {
        $this->authorize('manage', Interview::class);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'interview_type' => ['sometimes', Rule::in(['screening', 'technical', 'behavioral', 'panel', 'assessment_review', 'final'])],
            'mode' => ['sometimes', 'required', Rule::in(['online', 'onsite'])],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:15', 'max:480'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'meeting_url' => ['nullable', 'url', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'candidate_instructions' => ['nullable', 'string', 'max:2000'],
            'internal_agenda' => ['nullable', 'string', 'max:3000'],
            'interviewer_ids' => ['sometimes', 'array', 'min:1'],
            'interviewer_ids.*' => ['required', 'integer', 'exists:users,id'],
            'lead_interviewer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $updated = $this->service->update($interview, $validated, $request->user());

        return ApiResponse::success($updated, 'Interview updated successfully.');
    }

    public function cancel(Request $request, Interview $interview): JsonResponse
    {
        $this->authorize('manage', Interview::class);

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $cancelled = $this->service->cancel($interview, $validated['cancellation_reason'], $request->user());

        return ApiResponse::success($cancelled, 'Interview cancelled.');
    }

    public function complete(Request $request, Interview $interview): JsonResponse
    {
        $this->authorize('manage', Interview::class);
        $completed = $this->service->markCompleted($interview, $request->user());

        return ApiResponse::success($completed, 'Interview marked as completed.');
    }

    public function storeEvaluation(Request $request, Interview $interview): JsonResponse
    {
        $this->authorize('evaluate', $interview);

        $validated = $request->validate([
            'technical_skills_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'communication_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'problem_solving_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'relevant_experience_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'cultural_fit_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'interview_notes' => ['nullable', 'string', 'max:4000'],
            'strengths' => ['required', 'string', 'min:5', 'max:3000'],
            'weaknesses' => ['required', 'string', 'min:5', 'max:3000'],
            'overall_feedback' => ['required', 'string', 'min:10', 'max:4000'],
            'recommendation' => ['required', Rule::in(['strong_yes', 'yes', 'neutral', 'no', 'strong_no'])],
        ]);

        $evaluation = $this->service->submitEvaluation($interview, $validated, $request->user());

        return ApiResponse::created($evaluation, 'Interview evaluation submitted successfully.');
    }

    public function updateEvaluation(Request $request, Interview $interview, InterviewEvaluation $evaluation): JsonResponse
    {
        if ($evaluation->interview_id !== $interview->id) {
            abort(404);
        }

        $validated = $request->validate([
            'technical_skills_rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'communication_rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'problem_solving_rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'relevant_experience_rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'cultural_fit_rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'interview_notes' => ['nullable', 'string', 'max:4000'],
            'strengths' => ['sometimes', 'required', 'string', 'min:5', 'max:3000'],
            'weaknesses' => ['sometimes', 'required', 'string', 'min:5', 'max:3000'],
            'overall_feedback' => ['sometimes', 'required', 'string', 'min:10', 'max:4000'],
            'recommendation' => ['sometimes', 'required', Rule::in(['strong_yes', 'yes', 'neutral', 'no', 'strong_no'])],
        ]);

        $updated = $this->service->updateEvaluation($evaluation, $validated, $request->user());

        return ApiResponse::success($updated, 'Interview evaluation updated.');
    }
}
