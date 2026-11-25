<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\Department;
use App\Models\Job;
use App\Services\JobVacancyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCareerController extends Controller
{
    public function __construct(
        private readonly JobVacancyService $jobService
    ) {}

    public function jobs(Request $request): JsonResponse
    {
        $paginator = $this->jobService->paginatePublic($request->all());

        return ApiResponse::paginated($paginator, JobResource::class, 'Published job vacancies retrieved.');
    }

    public function showJob(string $slugOrId): JsonResponse
    {
        $job = Job::query()
            ->with(['department:id,code,name', 'skills'])
            ->where('status', 'published')
            ->where(function ($q) use ($slugOrId) {
                $q->where('slug', $slugOrId);
                if (ctype_digit($slugOrId)) {
                    $q->orWhere('id', (int) $slugOrId);
                }
            })
            ->firstOrFail();

        return ApiResponse::success(new JobResource($job), 'Job details retrieved.');
    }

    public function departments(): JsonResponse
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return ApiResponse::success($departments, 'Active departments retrieved.');
    }
}
