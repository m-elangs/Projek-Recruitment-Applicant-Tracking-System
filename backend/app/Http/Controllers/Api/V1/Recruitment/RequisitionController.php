<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreRequisitionRequest;
use App\Models\JobRequisition;
use App\Services\JobRequisitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequisitionController extends Controller
{
    public function __construct(
        private readonly JobRequisitionService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->paginate($request->all(), $request->user());

        return ApiResponse::paginated($paginator, null, 'Job requisitions retrieved.');
    }

    public function store(StoreRequisitionRequest $request): JsonResponse
    {
        $requisition = $this->service->create($request->validated(), $request->user());

        return ApiResponse::created($requisition, 'Job requisition submitted successfully.');
    }

    public function show(Request $request, JobRequisition $requisition): JsonResponse
    {
        $viewer = $request->user();
        if ($viewer->isHiringManager() && $requisition->requested_by !== $viewer->id && $requisition->department_id !== $viewer->department_id) {
            abort(403, 'You are not authorized to view this job requisition.');
        }

        return ApiResponse::success(
            $requisition->load(['department', 'requester:id,name,email', 'approver:id,name,email', 'jobs:id,code,title,status']),
            'Job requisition retrieved.'
        );
    }

    public function update(StoreRequisitionRequest $request, JobRequisition $requisition): JsonResponse
    {
        $updated = $this->service->update($requisition, $request->validated(), $request->user());

        return ApiResponse::success($updated, 'Job requisition updated.');
    }

    public function decide(Request $request, JobRequisition $requisition): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->service->decide(
            $requisition,
            $validated['decision'],
            $validated['rejection_reason'] ?? null,
            $request->user()
        );

        return ApiResponse::success($updated, "Job requisition {$validated['decision']}.");
    }
}
