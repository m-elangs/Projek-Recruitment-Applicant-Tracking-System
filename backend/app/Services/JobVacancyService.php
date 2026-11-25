<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Job;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JobVacancyService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    public function paginateInternal(array $filters, User $viewer): LengthAwarePaginator
    {
        $query = Job::query()
            ->with(['department', 'recruiter:id,name,email', 'hiringManager:id,name,email', 'skills'])
            ->withCount([
                'applications',
                'applications as active_applications_count' => fn ($q) => $q->where('is_active', true),
            ]);

        if ($viewer->isHiringManager()) {
            $query->where('hiring_manager_id', $viewer->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        if (! empty($filters['work_arrangement'])) {
            $query->where('work_arrangement', $filters['work_arrangement']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('location', 'like', $term);
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['created_at', 'published_at', 'closes_at', 'title'], true)
            ? $filters['sort_by']
            : 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function paginatePublic(array $filters): LengthAwarePaginator
    {
        $query = Job::query()
            ->with(['department:id,code,name', 'skills'])
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('closes_at')->orWhere('closes_at', '>', now());
            });

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        if (! empty($filters['work_arrangement'])) {
            $query->where('work_arrangement', $filters['work_arrangement']);
        }

        if (! empty($filters['location'])) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('location', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        return $query->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 12));
    }

    public function create(array $data, User $actor): Job
    {
        return DB::transaction(function () use ($data, $actor) {
            $nextNumber = (int) Job::withTrashed()->max('id') + 1;
            $code = sprintf('JOB-%s-%04d', now()->format('Y'), $nextNumber);
            $slug = Str::slug($data['title']).'-'.strtolower($code);

            $status = $data['status'] ?? 'draft';

            $job = Job::query()->create([
                'code' => $code,
                'slug' => $slug,
                'requisition_id' => $data['requisition_id'] ?? null,
                'department_id' => $data['department_id'],
                'recruiter_id' => $data['recruiter_id'] ?? $actor->id,
                'hiring_manager_id' => $data['hiring_manager_id'],
                'title' => $data['title'],
                'employment_type' => $data['employment_type'],
                'work_arrangement' => $data['work_arrangement'] ?? 'onsite',
                'location' => $data['location'],
                'experience_level' => $data['experience_level'] ?? 'mid',
                'description' => $data['description'],
                'requirements' => $data['requirements'],
                'responsibilities' => $data['responsibilities'] ?? null,
                'benefits' => $data['benefits'] ?? null,
                'salary_min' => $data['salary_min'] ?? null,
                'salary_max' => $data['salary_max'] ?? null,
                'salary_currency' => strtoupper($data['salary_currency'] ?? 'USD'),
                'is_salary_visible' => $data['is_salary_visible'] ?? true,
                'headcount' => $data['headcount'] ?? 1,
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
                'closes_at' => $data['closes_at'] ?? null,
            ]);

            if (! empty($data['skills']) && is_array($data['skills'])) {
                $this->syncSkills($job, $data['skills']);
            }

            $this->auditLog->log(
                eventType: 'job.created',
                auditable: $job,
                description: "Job vacancy {$job->code} ({$job->title}) created",
                actor: $actor,
                newValues: $job->only(['code', 'title', 'status', 'department_id', 'headcount'])
            );

            return $job->load(['department', 'recruiter:id,name,email', 'hiringManager:id,name,email', 'skills']);
        });
    }

    public function update(Job $job, array $data, User $actor): Job
    {
        return DB::transaction(function () use ($job, $data, $actor) {
            $oldValues = $job->only(['title', 'status', 'salary_min', 'salary_max', 'headcount', 'closes_at']);

            $skills = $data['skills'] ?? null;
            unset($data['skills'], $data['status']);

            $job->update($data);

            if (is_array($skills)) {
                $this->syncSkills($job, $skills);
            }

            $this->auditLog->log(
                eventType: 'job.updated',
                auditable: $job,
                description: "Job vacancy {$job->code} ({$job->title}) updated",
                actor: $actor,
                oldValues: $oldValues,
                newValues: $job->only(['title', 'status', 'salary_min', 'salary_max', 'headcount', 'closes_at'])
            );

            return $job->fresh(['department', 'recruiter:id,name,email', 'hiringManager:id,name,email', 'skills']);
        });
    }

    public function deleteDraft(Job $job, User $actor): void
    {
        if ($job->status !== 'draft') {
            throw new BusinessRuleException(
                'ONLY_DRAFT_DELETABLE',
                'Only draft job vacancies can be deleted. Published or historical vacancies must be paused or closed.',
                422
            );
        }

        DB::transaction(function () use ($job, $actor) {
            $this->auditLog->log(
                eventType: 'job.deleted_draft',
                auditable: $job,
                description: "Draft job vacancy {$job->code} ({$job->title}) deleted",
                actor: $actor
            );

            $job->delete();
        });
    }

    public function changeStatus(Job $job, string $targetStatus, User $actor): Job
    {
        $allowed = [
            'draft' => ['published'],
            'published' => ['paused', 'closed'],
            'paused' => ['published', 'closed'],
            'closed' => ['published'],
        ];

        if (! in_array($targetStatus, $allowed[$job->status] ?? [], true)) {
            throw new BusinessRuleException(
                'INVALID_JOB_STATUS_TRANSITION',
                "Cannot transition job vacancy from '{$job->status}' to '{$targetStatus}'.",
                422
            );
        }

        if ($targetStatus === 'published' && $job->closes_at !== null && $job->closes_at->isPast()) {
            throw new BusinessRuleException(
                'DEADLINE_IN_PAST',
                'Cannot publish a job vacancy whose application deadline has already passed.',
                422
            );
        }

        return DB::transaction(function () use ($job, $targetStatus, $actor) {
            $oldStatus = $job->status;

            $updates = ['status' => $targetStatus];
            if ($targetStatus === 'published' && $job->published_at === null) {
                $updates['published_at'] = now();
            }
            if ($targetStatus === 'closed') {
                $updates['closed_at'] = now();
            } elseif ($oldStatus === 'closed') {
                $updates['closed_at'] = null;
            }

            $job->update($updates);

            $this->auditLog->log(
                eventType: "job.{$targetStatus}",
                auditable: $job,
                description: "Job vacancy {$job->code} status changed from {$oldStatus} to {$targetStatus}",
                actor: $actor,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $targetStatus]
            );

            return $job->fresh(['department', 'recruiter:id,name,email', 'hiringManager:id,name,email', 'skills']);
        });
    }

    private function syncSkills(Job $job, array $skills): void
    {
        $job->skills()->delete();

        $normalized = [];
        $seen = [];
        foreach ($skills as $item) {
            $name = is_array($item) ? trim((string) ($item['skill_name'] ?? '')) : trim((string) $item);
            if ($name === '') {
                continue;
            }
            $key = strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $normalized[] = [
                'job_id' => $job->id,
                'skill_name' => $name,
                'is_required' => is_array($item) ? (bool) ($item['is_required'] ?? true) : true,
                'created_at' => now(),
            ];
        }

        if (! empty($normalized)) {
            $job->skills()->insert($normalized);
        }
    }
}
