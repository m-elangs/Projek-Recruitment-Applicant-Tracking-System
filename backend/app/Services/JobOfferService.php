<?php

namespace App\Services;

use App\Enums\StageSlug;
use App\Exceptions\BusinessRuleException;
use App\Models\Application;
use App\Models\CompanySetting;
use App\Models\JobOffer;
use App\Models\User;
use App\Notifications\JobOfferNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class JobOfferService
{
    public function __construct(
        private readonly ApplicationPipelineService $pipelineService,
        private readonly AuditLogService $auditLog
    ) {}

    public function paginate(array $filters, User $viewer): LengthAwarePaginator
    {
        $query = JobOffer::query()
            ->with([
                'application.candidate:id,user_id,first_name,last_name,email,phone',
                'application.job:id,code,title,hiring_manager_id,recruiter_id',
                'application.currentStage:id,slug,name',
                'department:id,code,name',
                'creator:id,name',
                'approver:id,name',
            ]);

        if ($viewer->isHiringManager()) {
            $query->whereHas('application.job', fn ($q) => $q->where('hiring_manager_id', $viewer->id));
        } elseif ($viewer->isCandidate()) {
            $query->whereHas('application.candidate', fn ($q) => $q->where('user_id', $viewer->id))
                ->whereIn('status', ['sent', 'accepted', 'declined', 'expired']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['job_id'])) {
            $jobId = (int) $filters['job_id'];
            $query->whereHas('application', fn ($q) => $q->where('job_id', $jobId));
        }

        return $query->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data, User $actor): JobOffer
    {
        $application = Application::query()
            ->with(['job', 'candidate.user', 'currentStage'])
            ->findOrFail($data['application_id']);

        if (! $application->is_active || $application->currentStage?->is_terminal) {
            throw new BusinessRuleException(
                'APPLICATION_INACTIVE',
                'Job offers can only be created for active applications.',
                422
            );
        }

        $hasActiveOffer = $application->offers()
            ->whereIn('status', ['draft', 'pending_approval', 'sent'])
            ->exists();

        if ($hasActiveOffer) {
            throw new BusinessRuleException(
                'ACTIVE_OFFER_EXISTS',
                'An active job offer already exists for this application.',
                409
            );
        }

        return DB::transaction(function () use ($application, $data, $actor) {
            $approvalRequired = (bool) CompanySetting::getValue('offer_approval_required', false);
            $requestApproval = (bool) ($data['request_approval'] ?? $approvalRequired);
            $sendImmediately = (bool) ($data['send_immediately'] ?? false);

            $nextNum = (int) JobOffer::withTrashed()->max('id') + 1;
            $offerNumber = sprintf('OFR-%s-%04d', now()->format('Y'), $nextNum);

            $initialStatus = $requestApproval ? 'pending_approval' : 'draft';

            $offer = JobOffer::query()->create([
                'offer_number' => $offerNumber,
                'application_id' => $application->id,
                'created_by' => $actor->id,
                'department_id' => $data['department_id'] ?? $application->job->department_id,
                'position_title' => $data['position_title'] ?? $application->job->title,
                'employment_type' => $data['employment_type'] ?? $application->job->employment_type,
                'base_salary' => $data['base_salary'],
                'bonus_amount' => $data['bonus_amount'] ?? null,
                'salary_currency' => strtoupper($data['salary_currency'] ?? $application->job->salary_currency ?? 'USD'),
                'pay_frequency' => $data['pay_frequency'] ?? 'annual',
                'benefits_summary' => $data['benefits_summary'] ?? null,
                'proposed_start_date' => $data['proposed_start_date'],
                'expires_at' => $data['expires_at'],
                'status' => $initialStatus,
            ]);

            $this->auditLog->log(
                eventType: 'offer.created',
                auditable: $offer,
                description: "Job offer {$offer->offer_number} created for {$application->application_number}",
                actor: $actor,
                newValues: ['offer_number' => $offerNumber, 'base_salary' => $offer->base_salary, 'status' => $initialStatus]
            );

            if ($sendImmediately && ! $requestApproval) {
                return $this->send($offer, $actor);
            }

            return $offer->load(['application.candidate', 'application.job', 'department', 'creator']);
        });
    }

    public function update(JobOffer $offer, array $data, User $actor): JobOffer
    {
        if (! in_array($offer->status, ['draft', 'pending_approval'], true)) {
            throw new BusinessRuleException('OFFER_LOCKED', 'Only draft or pending-approval offers can be edited.', 422);
        }

        $offer->update($data);

        $this->auditLog->log(
            eventType: 'offer.updated',
            auditable: $offer,
            description: "Job offer {$offer->offer_number} updated",
            actor: $actor
        );

        return $offer->fresh(['application.candidate', 'application.job', 'department', 'creator', 'approver']);
    }

    public function approve(JobOffer $offer, User $actor): JobOffer
    {
        if (! in_array($offer->status, ['pending_approval', 'draft'], true)) {
            throw new BusinessRuleException('INVALID_OFFER_STATE', 'This offer is not awaiting approval.', 422);
        }

        return DB::transaction(function () use ($offer, $actor) {
            $offer->update([
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'status' => 'draft',
            ]);

            $this->auditLog->log(
                eventType: 'offer.approved',
                auditable: $offer,
                description: "Job offer {$offer->offer_number} approved by {$actor->name}",
                actor: $actor
            );

            return $offer->fresh(['application.candidate', 'application.job', 'department', 'creator', 'approver']);
        });
    }

    public function send(JobOffer $offer, User $actor): JobOffer
    {
        $approvalRequired = (bool) CompanySetting::getValue('offer_approval_required', false);

        if ($offer->status === 'pending_approval' || ($approvalRequired && $offer->approved_at === null && ! $actor->isAdmin())) {
            throw new BusinessRuleException(
                'OFFER_APPROVAL_REQUIRED',
                'This job offer must be approved before being sent to the candidate.',
                422
            );
        }

        if ($offer->status !== 'draft') {
            throw new BusinessRuleException('INVALID_OFFER_STATE', "Cannot send an offer in '{$offer->status}' status.", 422);
        }

        if ($offer->expires_at->isPast()) {
            throw new BusinessRuleException('OFFER_ALREADY_EXPIRED', 'Offer expiration date must be in the future.', 422);
        }

        return DB::transaction(function () use ($offer, $actor) {
            $offer->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $application = $offer->application()->with(['currentStage', 'candidate.user'])->firstOrFail();

            if ($application->currentStage->slug !== StageSlug::OFFERED->value && ! $application->currentStage->is_terminal) {
                $this->pipelineService->transitionStage(
                    application: $application,
                    targetStageSlug: StageSlug::OFFERED->value,
                    actor: $actor,
                    reason: "Official job offer {$offer->offer_number} sent",
                    triggeredByOfferWorkflow: true
                );
            }

            $this->auditLog->log(
                eventType: 'offer.sent',
                auditable: $offer,
                description: "Job offer {$offer->offer_number} sent to candidate",
                actor: $actor
            );

            $offer->load(['application.candidate.user', 'application.job', 'department']);
            if ($offer->application?->candidate?->user) {
                $offer->application->candidate->user->notify(new JobOfferNotification($offer, 'sent', forCandidate: true));
            }

            return $offer;
        });
    }

    public function respond(
        JobOffer $offer,
        string $decision,
        ?string $declineReason,
        ?string $candidateNotes,
        User $candidateUser
    ): JobOffer {
        if ($offer->status !== 'sent') {
            throw new BusinessRuleException(
                'OFFER_NOT_RESPONDABLE',
                "This offer is currently '{$offer->status}' and cannot be accepted or declined.",
                422
            );
        }

        if ($offer->expires_at->isPast()) {
            $offer->update(['status' => 'expired']);
            throw new BusinessRuleException(
                'OFFER_EXPIRED',
                'This job offer has expired and can no longer be accepted.',
                422
            );
        }

        if ($decision === 'declined' && blank($declineReason)) {
            throw new BusinessRuleException('DECLINE_REASON_REQUIRED', 'Please provide a brief reason when declining the offer.', 422);
        }

        return DB::transaction(function () use ($offer, $decision, $declineReason, $candidateNotes, $candidateUser) {
            $offer->update([
                'status' => $decision,
                'responded_at' => now(),
                'decline_reason' => $decision === 'declined' ? $declineReason : null,
                'candidate_notes' => $candidateNotes,
            ]);

            $application = $offer->application()->with(['job.recruiter', 'job.hiringManager', 'currentStage'])->firstOrFail();

            if ($decision === 'accepted') {
                $this->pipelineService->transitionStage(
                    application: $application,
                    targetStageSlug: StageSlug::HIRED->value,
                    actor: $candidateUser,
                    reason: "Candidate accepted job offer {$offer->offer_number}",
                    triggeredByOfferWorkflow: true
                );
            } else {
                $this->pipelineService->transitionStage(
                    application: $application,
                    targetStageSlug: StageSlug::REJECTED->value,
                    actor: $candidateUser,
                    reason: $declineReason ?: 'Candidate declined job offer',
                    triggeredByOfferWorkflow: true
                );
            }

            $this->auditLog->log(
                eventType: "offer.{$decision}",
                auditable: $offer,
                description: "Candidate {$decision} job offer {$offer->offer_number}",
                actor: $candidateUser
            );

            $offer->load(['application.candidate', 'application.job.recruiter', 'application.job.hiringManager', 'department']);

            if ($offer->application->job?->recruiter) {
                $offer->application->job->recruiter->notify(new JobOfferNotification($offer, $decision, forCandidate: false));
            }
            if ($offer->application->job?->hiringManager && $offer->application->job->hiring_manager_id !== $offer->application->job->recruiter_id) {
                $offer->application->job->hiringManager->notify(new JobOfferNotification($offer, $decision, forCandidate: false));
            }

            return $offer;
        });
    }

    public function revoke(JobOffer $offer, User $actor): JobOffer
    {
        if (! in_array($offer->status, ['draft', 'pending_approval', 'sent'], true)) {
            throw new BusinessRuleException('CANNOT_REVOKE_OFFER', "Cannot revoke an offer in '{$offer->status}' status.", 422);
        }

        $offer->update(['status' => 'revoked']);

        $this->auditLog->log(
            eventType: 'offer.revoked',
            auditable: $offer,
            description: "Job offer {$offer->offer_number} revoked by {$actor->name}",
            actor: $actor
        );

        return $offer->fresh(['application.candidate', 'application.job', 'department']);
    }
}
