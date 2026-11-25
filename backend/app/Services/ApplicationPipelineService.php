<?php

namespace App\Services;

use App\Enums\StageSlug;
use App\Exceptions\BusinessRuleException;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\RecruitmentStage;
use App\Models\User;
use App\Notifications\ApplicationReceivedNotification;
use App\Notifications\ApplicationStageChangedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApplicationPipelineService
{
    /**
     * Allowed stage transitions from each non-terminal stage.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'applied' => ['screening', 'shortlisted', 'rejected', 'withdrawn'],
        'screening' => ['shortlisted', 'interview', 'rejected', 'withdrawn'],
        'shortlisted' => ['screening', 'interview', 'assessment', 'rejected', 'withdrawn'],
        'interview' => ['shortlisted', 'assessment', 'offered', 'rejected', 'withdrawn'],
        'assessment' => ['interview', 'offered', 'rejected', 'withdrawn'],
        'offered' => ['hired', 'rejected', 'withdrawn'],
        'hired' => [],
        'rejected' => [],
        'withdrawn' => [],
    ];

    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    public function paginate(array $filters, User $viewer): LengthAwarePaginator
    {
        $query = Application::query()
            ->with([
                'job:id,code,title,department_id,location,employment_type,hiring_manager_id,recruiter_id',
                'job.department:id,code,name',
                'candidate:id,user_id,first_name,last_name,email,phone,location,current_title,current_company,years_of_experience',
                'currentStage:id,slug,name,display_order,is_terminal',
                'cvDocument:id,original_name,mime_type,file_size_bytes',
            ]);

        if ($viewer->isHiringManager()) {
            $query->whereHas('job', fn ($q) => $q->where('hiring_manager_id', $viewer->id));
        } elseif ($viewer->isCandidate()) {
            $query->whereHas('candidate', fn ($q) => $q->where('user_id', $viewer->id));
        }

        if (! empty($filters['job_id'])) {
            $query->where('job_id', (int) $filters['job_id']);
        }

        if (! empty($filters['stage_id'])) {
            $query->where('current_stage_id', (int) $filters['stage_id']);
        }

        if (! empty($filters['stage_slug'])) {
            $slug = $filters['stage_slug'];
            $query->whereHas('currentStage', fn ($q) => $q->where('slug', $slug));
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('application_number', 'like', $term)
                    ->orWhereHas('candidate', function ($cq) use ($term) {
                        $cq->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    })
                    ->orWhereHas('job', fn ($jq) => $jq->where('title', 'like', $term));
            });
        }

        return $query->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getKanbanBoard(?int $jobId, User $viewer): Collection
    {
        $stages = RecruitmentStage::query()->orderBy('display_order')->get();

        $appsQuery = Application::query()
            ->with([
                'job:id,code,title,department_id,hiring_manager_id',
                'candidate:id,first_name,last_name,email,current_title,current_company,years_of_experience',
                'candidate.skills',
                'currentStage:id,slug,name,display_order,is_terminal',
                'interviews.evaluations',
            ]);

        if ($viewer->isHiringManager()) {
            $appsQuery->whereHas('job', fn ($q) => $q->where('hiring_manager_id', $viewer->id));
        }

        if ($jobId) {
            $appsQuery->where('job_id', $jobId);
        }

        $applications = $appsQuery->orderByDesc('stage_updated_at')->get()->groupBy('current_stage_id');

        return $stages->map(function (RecruitmentStage $stage) use ($applications) {
            $stageApps = $applications->get($stage->id, collect());

            return [
                'stage' => [
                    'id' => $stage->id,
                    'slug' => $stage->slug,
                    'name' => $stage->name,
                    'display_order' => $stage->display_order,
                    'is_terminal' => $stage->is_terminal,
                    'allowed_next_stages' => self::ALLOWED_TRANSITIONS[$stage->slug] ?? [],
                ],
                'count' => $stageApps->count(),
                'applications' => $stageApps->map(function (Application $app) {
                    $allEvals = $app->interviews->flatMap->evaluations;
                    $avgScore = $allEvals->isNotEmpty()
                        ? round((float) $allEvals->avg('average_rating'), 2)
                        : null;

                    return [
                        'id' => $app->id,
                        'application_number' => $app->application_number,
                        'job' => [
                            'id' => $app->job->id,
                            'code' => $app->job->code,
                            'title' => $app->job->title,
                        ],
                        'candidate' => [
                            'id' => $app->candidate->id,
                            'full_name' => $app->candidate->full_name,
                            'email' => $app->candidate->email,
                            'current_title' => $app->candidate->current_title,
                            'current_company' => $app->candidate->current_company,
                            'years_of_experience' => $app->candidate->years_of_experience,
                            'skills' => $app->candidate->skills->pluck('skill_name')->take(4)->values(),
                        ],
                        'hiring_manager_recommendation' => $app->hiring_manager_recommendation,
                        'average_evaluation_rating' => $avgScore,
                        'applied_at' => $app->applied_at?->toIso8601String(),
                        'stage_updated_at' => $app->stage_updated_at?->toIso8601String(),
                    ];
                })->values(),
            ];
        });
    }

    public function submit(Job $job, Candidate $candidate, array $data, User $actor): Application
    {
        if (! $job->isAcceptingApplications()) {
            throw new BusinessRuleException(
                'JOB_NOT_ACCEPTING_APPLICATIONS',
                'This job vacancy is closed, paused, unpublished, or past its application deadline.',
                422
            );
        }

        return DB::transaction(function () use ($job, $candidate, $data, $actor) {
            $duplicateExists = Application::query()
                ->where('candidate_id', $candidate->id)
                ->where('job_id', $job->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->exists();

            if ($duplicateExists) {
                throw new BusinessRuleException(
                    'DUPLICATE_ACTIVE_APPLICATION',
                    'An active application already exists for this candidate and job vacancy.',
                    409
                );
            }

            $cvDocumentId = $data['cv_document_id'] ?? $candidate->primaryCv()->value('id');
            if ($cvDocumentId !== null) {
                $belongsToCandidate = $candidate->documents()->where('id', $cvDocumentId)->exists();
                if (! $belongsToCandidate) {
                    throw new BusinessRuleException('INVALID_CV_DOCUMENT', 'Selected CV document does not belong to this candidate.', 422);
                }
            } elseif ($actor->isCandidate()) {
                throw new BusinessRuleException(
                    'CV_REQUIRED',
                    'Please upload a CV before submitting your job application.',
                    422
                );
            }

            $appliedStage = RecruitmentStage::query()
                ->where('slug', StageSlug::APPLIED->value)
                ->firstOrFail();

            $nextNumber = (int) Application::withTrashed()->max('id') + 1;
            $appNumber = sprintf('APP-%s-%05d', now()->format('Y'), $nextNumber);
            $now = now();

            $application = Application::query()->create([
                'application_number' => $appNumber,
                'job_id' => $job->id,
                'candidate_id' => $candidate->id,
                'cv_document_id' => $cvDocumentId,
                'current_stage_id' => $appliedStage->id,
                'source' => $data['source'] ?? $candidate->source ?? 'careers_portal',
                'cover_letter' => $data['cover_letter'] ?? null,
                'expected_salary' => $data['expected_salary'] ?? $candidate->expected_salary,
                'available_from' => $data['available_from'] ?? null,
                'is_active' => true,
                'applied_at' => $now,
                'stage_updated_at' => $now,
            ]);

            $application->histories()->create([
                'from_stage_id' => null,
                'to_stage_id' => $appliedStage->id,
                'changed_by' => $actor->id,
                'reason' => 'Initial application submitted',
                'notes' => null,
                'time_in_previous_stage_seconds' => 0,
                'created_at' => $now,
            ]);

            $this->auditLog->log(
                eventType: 'application.submitted',
                auditable: $application,
                description: "Application {$application->application_number} submitted by {$candidate->full_name} for {$job->title}",
                actor: $actor,
                newValues: ['job_id' => $job->id, 'candidate_id' => $candidate->id, 'stage' => 'applied']
            );

            $application->load(['job.recruiter', 'job.hiringManager', 'candidate.user', 'currentStage']);

            if ($candidate->user) {
                $candidate->user->notify(new ApplicationReceivedNotification($application, forCandidate: true));
            }
            if ($job->recruiter && $job->recruiter->id !== $actor->id) {
                $job->recruiter->notify(new ApplicationReceivedNotification($application, forCandidate: false));
            }
            if ($job->hiringManager && $job->hiringManager->id !== $job->recruiter_id) {
                $job->hiringManager->notify(new ApplicationReceivedNotification($application, forCandidate: false));
            }

            return $application;
        });
    }

    public function transitionStage(
        Application $application,
        string $targetStageSlug,
        User $actor,
        ?string $reason = null,
        ?string $notes = null,
        bool $triggeredByOfferWorkflow = false
    ): Application {
        return DB::transaction(function () use ($application, $targetStageSlug, $actor, $reason, $notes, $triggeredByOfferWorkflow) {
            $application = Application::query()
                ->with(['currentStage', 'job', 'candidate.user', 'offers'])
                ->lockForUpdate()
                ->findOrFail($application->id);

            $fromStage = $application->currentStage;
            $toStage = RecruitmentStage::query()->where('slug', $targetStageSlug)->firstOrFail();

            if ($fromStage->id === $toStage->id) {
                throw new BusinessRuleException('SAME_STAGE', "Application is already in the {$toStage->name} stage.", 422);
            }

            if ($fromStage->is_terminal) {
                throw new BusinessRuleException(
                    'TERMINAL_STAGE_LOCKED',
                    "Applications in terminal stage '{$fromStage->name}' cannot be transitioned further.",
                    422
                );
            }

            $allowedTargets = self::ALLOWED_TRANSITIONS[$fromStage->slug] ?? [];
            if (! in_array($toStage->slug, $allowedTargets, true) && ! ($triggeredByOfferWorkflow && in_array($toStage->slug, ['offered', 'hired'], true))) {
                throw new BusinessRuleException(
                    'INVALID_STAGE_TRANSITION',
                    "Transition from '{$fromStage->name}' to '{$toStage->name}' is not permitted by recruitment workflow rules.",
                    422,
                    ['from' => $fromStage->slug, 'to' => $toStage->slug, 'allowed' => $allowedTargets]
                );
            }

            if ($toStage->slug === StageSlug::REJECTED->value && blank($reason)) {
                throw new BusinessRuleException(
                    'REJECTION_REASON_REQUIRED',
                    'A rejection reason is required when moving an application to Rejected.',
                    422
                );
            }

            if ($toStage->slug === StageSlug::HIRED->value && ! $triggeredByOfferWorkflow) {
                $hasAcceptedOffer = $application->offers()->where('status', 'accepted')->exists();
                if (! $hasAcceptedOffer) {
                    throw new BusinessRuleException(
                        'ACCEPTED_OFFER_REQUIRED',
                        'An application can only reach Hired status after a job offer has been accepted.',
                        422
                    );
                }
            }

            $now = now();
            $secondsInStage = $application->stage_updated_at
                ? max(0, $now->diffInSeconds($application->stage_updated_at))
                : 0;

            $updates = [
                'current_stage_id' => $toStage->id,
                'stage_updated_at' => $now,
                'is_active' => ! $toStage->is_terminal,
            ];

            if ($toStage->slug === StageSlug::REJECTED->value) {
                $updates['rejected_at'] = $now;
                $updates['rejection_reason'] = $reason;
            } elseif ($toStage->slug === StageSlug::WITHDRAWN->value) {
                $updates['withdrawn_at'] = $now;
                $updates['withdrawal_reason'] = $reason;
            } elseif ($toStage->slug === StageSlug::HIRED->value) {
                $updates['hired_at'] = $now;
            }

            $application->update($updates);

            $application->histories()->create([
                'from_stage_id' => $fromStage->id,
                'to_stage_id' => $toStage->id,
                'changed_by' => $actor->id,
                'reason' => $reason,
                'notes' => $notes,
                'time_in_previous_stage_seconds' => $secondsInStage,
                'created_at' => $now,
            ]);

            if ($toStage->slug === StageSlug::HIRED->value) {
                $application->job()->increment('hired_count');
            }

            if (in_array($toStage->slug, [StageSlug::REJECTED->value, StageSlug::WITHDRAWN->value], true)) {
                $application->interviews()
                    ->whereIn('status', ['scheduled', 'rescheduled'])
                    ->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => "Application moved to {$toStage->name}",
                    ]);

                $application->offers()
                    ->whereIn('status', ['draft', 'pending_approval', 'sent'])
                    ->update(['status' => 'revoked']);
            }

            $this->auditLog->log(
                eventType: 'application.stage_changed',
                auditable: $application,
                description: "Application {$application->application_number} transitioned from {$fromStage->name} to {$toStage->name}",
                actor: $actor,
                oldValues: ['stage' => $fromStage->slug],
                newValues: ['stage' => $toStage->slug, 'reason' => $reason]
            );

            if ($application->candidate?->user) {
                $application->candidate->user->notify(
                    new ApplicationStageChangedNotification($application, $fromStage->name, $toStage->name)
                );
            }

            return $application->fresh([
                'job.department',
                'candidate.skills',
                'currentStage',
                'cvDocument',
                'histories.fromStage',
                'histories.toStage',
                'histories.actor:id,name,email',
            ]);
        });
    }

    public function updateHiringManagerRecommendation(
        Application $application,
        string $recommendation,
        ?string $notes,
        User $actor
    ): Application {
        return DB::transaction(function () use ($application, $recommendation, $notes, $actor) {
            $old = $application->hiring_manager_recommendation;

            $application->update([
                'hiring_manager_recommendation' => $recommendation,
                'hiring_manager_notes' => $notes,
            ]);

            $this->auditLog->log(
                eventType: 'application.hm_recommended',
                auditable: $application,
                description: "Hiring Manager {$actor->name} submitted recommendation '{$recommendation}' on {$application->application_number}",
                actor: $actor,
                oldValues: ['recommendation' => $old],
                newValues: ['recommendation' => $recommendation]
            );

            return $application->fresh(['currentStage', 'job', 'candidate']);
        });
    }
}
