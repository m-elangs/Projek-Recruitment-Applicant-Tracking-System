<?php

namespace App\Http\Controllers\Api\V1\Offers;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use App\Services\JobOfferService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobOfferController extends Controller
{
    public function __construct(
        private readonly JobOfferService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->paginate($request->all(), $request->user());

        return ApiResponse::paginated($paginator, null, 'Job offers retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage', JobOffer::class);

        $validated = $request->validate([
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_title' => ['nullable', 'string', 'max:180'],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'base_salary' => ['required', 'numeric', 'gt:0'],
            'bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'pay_frequency' => ['nullable', Rule::in(['monthly', 'annual'])],
            'benefits_summary' => ['nullable', 'string', 'max:3000'],
            'proposed_start_date' => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after:now'],
            'request_approval' => ['nullable', 'boolean'],
            'send_immediately' => ['nullable', 'boolean'],
        ]);

        $offer = $this->service->create($validated, $request->user());

        return ApiResponse::created($offer, 'Job offer created successfully.');
    }

    public function show(JobOffer $jobOffer): JsonResponse
    {
        $jobOffer->loadMissing(['application.job', 'application.candidate']);
        $this->authorize('view', $jobOffer);

        $jobOffer->load([
            'application.candidate:id,first_name,last_name,email,phone',
            'application.job:id,code,title,location,employment_type',
            'application.currentStage',
            'department:id,code,name',
            'creator:id,name,email',
            'approver:id,name,email',
        ]);

        return ApiResponse::success($jobOffer, 'Job offer details retrieved.');
    }

    public function update(Request $request, JobOffer $jobOffer): JsonResponse
    {
        $this->authorize('manage', JobOffer::class);

        $validated = $request->validate([
            'position_title' => ['sometimes', 'required', 'string', 'max:180'],
            'employment_type' => ['sometimes', Rule::in(['full_time', 'part_time', 'contract', 'internship'])],
            'base_salary' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'pay_frequency' => ['nullable', Rule::in(['monthly', 'annual'])],
            'benefits_summary' => ['nullable', 'string', 'max:3000'],
            'proposed_start_date' => ['sometimes', 'required', 'date'],
            'expires_at' => ['sometimes', 'required', 'date'],
        ]);

        $updated = $this->service->update($jobOffer, $validated, $request->user());

        return ApiResponse::success($updated, 'Job offer updated.');
    }

    public function approve(Request $request, JobOffer $jobOffer): JsonResponse
    {
        $jobOffer->loadMissing('application.job');
        $this->authorize('approve', $jobOffer);

        $approved = $this->service->approve($jobOffer, $request->user());

        return ApiResponse::success($approved, 'Job offer approved.');
    }

    public function send(Request $request, JobOffer $jobOffer): JsonResponse
    {
        $this->authorize('manage', JobOffer::class);
        $sent = $this->service->send($jobOffer, $request->user());

        return ApiResponse::success($sent, 'Job offer sent to candidate.');
    }

    public function revoke(Request $request, JobOffer $jobOffer): JsonResponse
    {
        $this->authorize('manage', JobOffer::class);
        $revoked = $this->service->revoke($jobOffer, $request->user());

        return ApiResponse::success($revoked, 'Job offer revoked.');
    }
}
