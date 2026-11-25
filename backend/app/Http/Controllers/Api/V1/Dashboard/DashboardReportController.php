<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardReportController extends Controller
{
    public function __construct(
        private readonly AnalyticsReportService $analyticsService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getDashboardMetrics($request->user());

        return ApiResponse::success($data, 'Dashboard metrics retrieved.');
    }

    public function fullReport(Request $request): JsonResponse
    {
        $report = $this->analyticsService->getFullAnalyticsReport($request->all(), $request->user());

        return ApiResponse::success($report, 'Recruitment analytics report retrieved.');
    }

    public function applicationsByJob(Request $request): JsonResponse
    {
        $report = $this->analyticsService->getFullAnalyticsReport($request->all(), $request->user());

        return ApiResponse::success($report['applications_by_job'], 'Applications by job retrieved.');
    }

    public function funnel(Request $request): JsonResponse
    {
        $report = $this->analyticsService->getFullAnalyticsReport($request->all(), $request->user());

        return ApiResponse::success($report['candidates_by_stage'], 'Recruitment funnel retrieved.');
    }

    public function sources(Request $request): JsonResponse
    {
        $report = $this->analyticsService->getFullAnalyticsReport($request->all(), $request->user());

        return ApiResponse::success($report['candidate_sources'], 'Candidate sources report retrieved.');
    }

    public function departments(Request $request): JsonResponse
    {
        $report = $this->analyticsService->getFullAnalyticsReport($request->all(), $request->user());

        return ApiResponse::success($report['department_performance'], 'Department performance report retrieved.');
    }
}
