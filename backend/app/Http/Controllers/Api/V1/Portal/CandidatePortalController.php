<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Enums\StageSlug;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreCandidateRequest;
use App\Http\Requests\Recruitment\SubmitApplicationRequest;
use App\Http\Requests\Recruitment\UploadDocumentRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobOffer;
use App\Services\ApplicationPipelineService;
use App\Services\CandidateService;
use App\Services\DocumentVaultService;
use App\Services\JobOfferService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CandidatePortalController extends Controller
{
    public function __construct(
        private readonly CandidateService $candidateService,
        private readonly DocumentVaultService $documentService,
        private readonly ApplicationPipelineService $pipelineService,
        private readonly JobOfferService $offerService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        $activeApplications = $candidate->applications()->where('is_active', true)->count();
        $totalApplications = $candidate->applications()->count();

        $upcomingInterviews = Interview::query()
            ->with(['application.job:id,code,title,location', 'panelUsers:id,name,job_title'])
            ->whereHas('application', fn ($q) => $q->where('candidate_id', $candidate->id))
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->where('scheduled_at', '>=', now()->subHours(1))
            ->orderBy('scheduled_at')
            ->get()
            ->each(fn (Interview $iv) => $iv->makeHidden(['internal_agenda']));

        $pendingOffers = JobOffer::query()
            ->with(['application.job:id,code,title', 'department:id,code,name'])
            ->whereHas('application', fn ($q) => $q->where('candidate_id', $candidate->id))
            ->where('status', 'sent')
            ->orderBy('expires_at')
            ->get();

        $recentApplications = $candidate->applications()
            ->with(['job.department:id,code,name', 'currentStage'])
            ->orderByDesc('applied_at')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'summary' => [
                'total_applications' => $totalApplications,
                'active_applications' => $activeApplications,
                'upcoming_interviews' => $upcomingInterviews->count(),
                'pending_offers' => $pendingOffers->count(),
                'has_primary_cv' => $candidate->primaryCv()->exists(),
            ],
            'upcoming_interviews' => $upcomingInterviews,
            'pending_offers' => $pendingOffers,
            'recent_applications' => ApplicationResource::collection($recentApplications),
        ], 'Candidate dashboard retrieved.');
    }

    public function profile(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request)->load([
            'skills',
            'experiences',
            'educations',
            'documents',
            'primaryCv',
        ]);

        return ApiResponse::success($candidate, 'Candidate profile retrieved.');
    }

    public function updateProfile(StoreCandidateRequest $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);
        $data = $request->validated();
        unset($data['source']);

        $updated = $this->candidateService->update($candidate, $data, $request->user());

        return ApiResponse::success($updated, 'Your profile has been updated.');
    }

    public function uploadDocument(UploadDocumentRequest $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        $doc = $this->documentService->store(
            candidate: $candidate,
            file: $request->file('file'),
            documentType: $request->input('document_type', 'cv'),
            makePrimaryCv: (bool) $request->boolean('is_primary_cv', true),
            actor: $request->user()
        );

        return ApiResponse::created($doc, 'Document uploaded to your profile.');
    }

    public function applications(Request $request): JsonResponse
    {
        $paginator = $this->pipelineService->paginate($request->all(), $request->user());

        return ApiResponse::paginated($paginator, ApplicationResource::class, 'Your applications retrieved.');
    }

    public function submitApplication(SubmitApplicationRequest $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);
        $validated = $request->validated();
        $job = Job::query()->findOrFail($validated['job_id']);

        $application = $this->pipelineService->submit($job, $candidate, $validated, $request->user());

        return ApiResponse::created(new ApplicationResource($application), 'Your application has been submitted.');
    }

    public function showApplication(Request $request, Application $application): JsonResponse
    {
        $application->loadMissing(['candidate', 'job']);
        $this->authorize('view', $application);

        $application->load([
            'job.department',
            'currentStage',
            'cvDocument',
            'histories.fromStage',
            'histories.toStage',
            'interviews.panelUsers:id,name,job_title',
            'offers.department:id,code,name',
        ]);

        return ApiResponse::success(new ApplicationResource($application), 'Application details retrieved.');
    }

    public function withdrawApplication(Request $request, Application $application): JsonResponse
    {
        $application->loadMissing('candidate');
        $this->authorize('withdraw', $application);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $this->pipelineService->transitionStage(
            application: $application,
            targetStageSlug: StageSlug::WITHDRAWN->value,
            actor: $request->user(),
            reason: $validated['reason'] ?? 'Candidate withdrew application'
        );

        return ApiResponse::success(new ApplicationResource($updated), 'Your application has been withdrawn.');
    }

    public function interviews(Request $request): JsonResponse
    {
        $candidate = $this->resolveCandidate($request);

        $interviews = Interview::query()
            ->with(['application.job.department:id,code,name', 'panelUsers:id,name,job_title'])
            ->whereHas('application', fn ($q) => $q->where('candidate_id', $candidate->id))
            ->orderByDesc('scheduled_at')
            ->get()
            ->each(fn (Interview $iv) => $iv->makeHidden(['internal_agenda']));

        return ApiResponse::success($interviews, 'Your interview schedule retrieved.');
    }

    public function respondOffer(Request $request, JobOffer $offer): JsonResponse
    {
        $offer->loadMissing('application.candidate');
        $this->authorize('respond', $offer);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'declined'])],
            'decline_reason' => ['nullable', 'string', 'max:255'],
            'candidate_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->offerService->respond(
            offer: $offer,
            decision: $validated['decision'],
            declineReason: $validated['decline_reason'] ?? null,
            candidateNotes: $validated['candidate_notes'] ?? null,
            candidateUser: $request->user()
        );

        return ApiResponse::success($updated, "Job offer {$validated['decision']}.");
    }

    private function resolveCandidate(Request $request): Candidate
    {
        $candidate = $request->user()?->candidate;

        if (! $candidate) {
            throw new BusinessRuleException('CANDIDATE_PROFILE_MISSING', 'Candidate profile not found for this account.', 404);
        }

        return $candidate;
    }
}
