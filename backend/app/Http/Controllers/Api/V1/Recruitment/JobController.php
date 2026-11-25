<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreJobRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Models\User;
use App\Services\JobVacancyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function __construct(
        private readonly JobVacancyService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Job::class);
        $paginator = $this->service->paginateInternal($request->all(), $request->user());

        return ApiResponse::paginated($paginator, JobResource::class, 'Job vacancies retrieved.');
    }

    public function store(StoreJobRequest $request): JsonResponse
    {
        $this->authorize('create', Job::class);
        $job = $this->service->create($request->validated(), $request->user());

        return ApiResponse::created(new JobResource($job), 'Job vacancy created successfully.');
    }

    public function show(Job $job): JsonResponse
    {
        $this->authorize('view', $job);
        $job->load([
            'department',
            'recruiter:id,name,email',
            'hiringManager:id,name,email',
            'skills',
            'requisition',
        ])->loadCount([
            'applications',
            'applications as active_applications_count' => fn ($q) => $q->where('is_active', true),
        ]);

        return ApiResponse::success(new JobResource($job), 'Job vacancy details retrieved.');
    }

    public function update(StoreJobRequest $request, Job $job): JsonResponse
    {
        $this->authorize('update', $job);
        $updated = $this->service->update($job, $request->validated(), $request->user());

        return ApiResponse::success(new JobResource($updated), 'Job vacancy updated successfully.');
    }

    public function destroy(Request $request, Job $job): JsonResponse
    {
        if ($job->status === 'draft') {
            $this->authorize('delete', $job);
        } elseif (! $request->user()->hasPermission('jobs.delete_draft')) {
            abort(403, 'You do not have permission to delete job vacancies.');
        }

        $this->service->deleteDraft($job, $request->user());

        return ApiResponse::success(null, 'Draft job vacancy deleted successfully.');
    }

    public function publish(Request $request, Job $job): JsonResponse
    {
        $this->authorize('update', $job);
        $updated = $this->service->changeStatus($job, 'published', $request->user());

        return ApiResponse::success(new JobResource($updated), 'Job vacancy published.');
    }

    public function pause(Request $request, Job $job): JsonResponse
    {
        $this->authorize('update', $job);
        $updated = $this->service->changeStatus($job, 'paused', $request->user());

        return ApiResponse::success(new JobResource($updated), 'Job vacancy paused.');
    }

    public function close(Request $request, Job $job): JsonResponse
    {
        $this->authorize('update', $job);
        $updated = $this->service->changeStatus($job, 'closed', $request->user());

        return ApiResponse::success(new JobResource($updated), 'Job vacancy closed.');
    }

    public function assignableStaff(): JsonResponse
    {
        $staff = User::query()
            ->with('role:id,slug,name', 'department:id,code,name')
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', [
                RoleSlug::SYSTEM_ADMIN->value,
                RoleSlug::RECRUITER->value,
                RoleSlug::HIRING_MANAGER->value,
            ]))
            ->orderBy('name')
            ->get(['id', 'role_id', 'department_id', 'name', 'email', 'job_title']);

        return ApiResponse::success($staff, 'Assignable internal staff retrieved.');
    }
}
