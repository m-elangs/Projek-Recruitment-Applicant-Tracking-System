<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\JobRequisition;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class JobRequisitionService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    public function paginate(array $filters, User $viewer): LengthAwarePaginator
    {
        $query = JobRequisition::query()
            ->with(['department', 'requester', 'approver'])
            ->withCount('jobs');

        if ($viewer->isHiringManager()) {
            $query->where(function ($q) use ($viewer) {
                $q->where('requested_by', $viewer->id);
                if ($viewer->department_id) {
                    $q->orWhere('department_id', $viewer->department_id);
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('requisition_code', 'like', $term)
                    ->orWhere('location', 'like', $term);
            });
        }

        return $query->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data, User $actor): JobRequisition
    {
        return DB::transaction(function () use ($data, $actor) {
            $nextNumber = (int) JobRequisition::withTrashed()->max('id') + 1;
            $code = sprintf('REQ-%s-%04d', now()->format('Y'), $nextNumber);

            $requisition = JobRequisition::query()->create([
                'requisition_code' => $code,
                'department_id' => $data['department_id'],
                'requested_by' => $actor->id,
                'title' => $data['title'],
                'headcount' => $data['headcount'] ?? 1,
                'employment_type' => $data['employment_type'],
                'work_arrangement' => $data['work_arrangement'] ?? 'onsite',
                'location' => $data['location'],
                'budget_min' => $data['budget_min'] ?? null,
                'budget_max' => $data['budget_max'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'USD'),
                'priority' => $data['priority'] ?? 'medium',
                'target_start_date' => $data['target_start_date'] ?? null,
                'justification' => $data['justification'],
                'requirements' => $data['requirements'] ?? null,
                'status' => $data['status'] ?? 'pending_approval',
            ]);

            $this->auditLog->log(
                eventType: 'requisition.created',
                auditable: $requisition,
                description: "Job requisition {$requisition->requisition_code} ({$requisition->title}) submitted",
                actor: $actor,
                newValues: $requisition->only(['requisition_code', 'title', 'headcount', 'status'])
            );

            return $requisition->load(['department', 'requester', 'approver']);
        });
    }

    public function update(JobRequisition $requisition, array $data, User $actor): JobRequisition
    {
        if (! in_array($requisition->status, ['draft', 'pending_approval'], true)) {
            throw new BusinessRuleException(
                'REQUISITION_LOCKED',
                'Only draft or pending requisitions can be edited.',
                422
            );
        }

        if ($actor->isHiringManager() && $requisition->requested_by !== $actor->id) {
            throw new BusinessRuleException('FORBIDDEN', 'You can only edit requisitions you submitted.', 403);
        }

        return DB::transaction(function () use ($requisition, $data, $actor) {
            $old = $requisition->only(['title', 'headcount', 'budget_min', 'budget_max', 'priority', 'status']);
            $requisition->update($data);

            $this->auditLog->log(
                eventType: 'requisition.updated',
                auditable: $requisition,
                description: "Job requisition {$requisition->requisition_code} updated",
                actor: $actor,
                oldValues: $old,
                newValues: $requisition->only(['title', 'headcount', 'budget_min', 'budget_max', 'priority', 'status'])
            );

            return $requisition->fresh(['department', 'requester', 'approver']);
        });
    }

    public function decide(JobRequisition $requisition, string $decision, ?string $rejectionReason, User $actor): JobRequisition
    {
        if ($requisition->status !== 'pending_approval') {
            throw new BusinessRuleException(
                'INVALID_REQUISITION_STATE',
                'Only requisitions awaiting approval can be approved or rejected.',
                422
            );
        }

        if ($decision === 'rejected' && blank($rejectionReason)) {
            throw new BusinessRuleException(
                'REJECTION_REASON_REQUIRED',
                'A rejection reason is required when rejecting a job requisition.',
                422
            );
        }

        return DB::transaction(function () use ($requisition, $decision, $rejectionReason, $actor) {
            $oldStatus = $requisition->status;

            $requisition->update([
                'status' => $decision,
                'approved_by' => $actor->id,
                'approved_at' => $decision === 'approved' ? now() : null,
                'rejection_reason' => $decision === 'rejected' ? $rejectionReason : null,
            ]);

            $this->auditLog->log(
                eventType: "requisition.{$decision}",
                auditable: $requisition,
                description: "Job requisition {$requisition->requisition_code} {$decision} by {$actor->name}",
                actor: $actor,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $decision, 'rejection_reason' => $rejectionReason]
            );

            return $requisition->fresh(['department', 'requester', 'approver']);
        });
    }
}
