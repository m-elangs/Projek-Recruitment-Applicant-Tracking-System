<?php

use App\Http\Controllers\Api\V1\Admin\AdminGovernanceController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardReportController;
use App\Http\Controllers\Api\V1\Interviews\InterviewController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Offers\JobOfferController;
use App\Http\Controllers\Api\V1\Pipeline\ApplicationController;
use App\Http\Controllers\Api\V1\Portal\CandidatePortalController;
use App\Http\Controllers\Api\V1\Public\PublicCareerController;
use App\Http\Controllers\Api\V1\Recruitment\CandidateController;
use App\Http\Controllers\Api\V1\Recruitment\JobController;
use App\Http\Controllers\Api\V1\Recruitment\RequisitionController;
use App\Models\RecruitmentStage;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => ApiResponse::success(['status' => 'ok', 'service' => 'TalentFlow ATS API']));

    // 1. Public Careers Portal Endpoints (Unauthenticated)
    Route::prefix('public')->group(function () {
        Route::get('/jobs', [PublicCareerController::class, 'jobs']);
        Route::get('/jobs/{slugOrId}', [PublicCareerController::class, 'showJob']);
        Route::get('/departments', [PublicCareerController::class, 'departments']);
    });

    // 2. Authentication & Password Recovery
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
            Route::post('/reset-password', [PasswordResetController::class, 'reset']);
        });

        Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/profile', [AuthController::class, 'updateProfile'])
                ->middleware('permission:profile.manage_own');
        });
    });

    // 3. Authenticated Endpoints
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
        Route::get('/stages', fn () => ApiResponse::success(
            RecruitmentStage::query()->orderBy('display_order')->get(),
            'Recruitment stages retrieved.'
        ));

        // Notifications (All authenticated roles)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::patch('/read-all', [NotificationController::class, 'markAllRead']);
            Route::patch('/{id}/read', [NotificationController::class, 'markRead']);
        });

        // Private Document Access (Policy-enforced)
        Route::get('/documents/{document}/download', [CandidateController::class, 'downloadDocument']);
        Route::delete('/documents/{document}', [CandidateController::class, 'destroyDocument']);

        // Candidate Self-Service Portal
        Route::prefix('portal')->middleware('role:candidate')->group(function () {
            Route::get('/dashboard', [CandidatePortalController::class, 'dashboard']);
            Route::get('/profile', [CandidatePortalController::class, 'profile']);
            Route::put('/profile', [CandidatePortalController::class, 'updateProfile']);
            Route::post('/documents', [CandidatePortalController::class, 'uploadDocument']);
            Route::get('/applications', [CandidatePortalController::class, 'applications']);
            Route::post('/applications', [CandidatePortalController::class, 'submitApplication']);
            Route::get('/applications/{application}', [CandidatePortalController::class, 'showApplication']);
            Route::patch('/applications/{application}/withdraw', [CandidatePortalController::class, 'withdrawApplication']);
            Route::get('/interviews', [CandidatePortalController::class, 'interviews']);
            Route::patch('/offers/{offer}/respond', [CandidatePortalController::class, 'respondOffer']);
        });

        // Internal Dashboard & Analytics
        Route::get('/dashboard', [DashboardReportController::class, 'dashboard'])
            ->middleware('permission:dashboard.view_internal');

        Route::prefix('reports')->middleware('permission:reports.view')->group(function () {
            Route::get('/', [DashboardReportController::class, 'fullReport']);
            Route::get('/overview', [DashboardReportController::class, 'fullReport']);
            Route::get('/applications-by-job', [DashboardReportController::class, 'applicationsByJob']);
            Route::get('/funnel', [DashboardReportController::class, 'funnel']);
            Route::get('/sources', [DashboardReportController::class, 'sources']);
            Route::get('/departments', [DashboardReportController::class, 'departments']);
        });

        // Job Requisitions
        Route::prefix('requisitions')->group(function () {
            Route::get('/', [RequisitionController::class, 'index'])->middleware('permission:requisitions.view');
            Route::post('/', [RequisitionController::class, 'store'])->middleware('permission:requisitions.create');
            Route::get('/{requisition}', [RequisitionController::class, 'show'])->middleware('permission:requisitions.view');
            Route::put('/{requisition}', [RequisitionController::class, 'update'])->middleware('permission:requisitions.create');
            Route::patch('/{requisition}/decision', [RequisitionController::class, 'decide'])->middleware('permission:requisitions.approve');
        });

        // Internal Job Vacancies
        Route::prefix('jobs')->group(function () {
            Route::get('/assignable-staff', [JobController::class, 'assignableStaff'])->middleware('permission:jobs.view_internal');
            Route::get('/', [JobController::class, 'index'])->middleware('permission:jobs.view_internal');
            Route::post('/', [JobController::class, 'store'])->middleware('permission:jobs.manage');
            Route::get('/{job}', [JobController::class, 'show'])->middleware('permission:jobs.view_internal');
            Route::put('/{job}', [JobController::class, 'update'])->middleware('permission:jobs.manage');
            Route::delete('/{job}', [JobController::class, 'destroy'])->middleware('permission:jobs.delete_draft');
            Route::patch('/{job}/publish', [JobController::class, 'publish'])->middleware('permission:jobs.manage');
            Route::patch('/{job}/pause', [JobController::class, 'pause'])->middleware('permission:jobs.manage');
            Route::patch('/{job}/close', [JobController::class, 'close'])->middleware('permission:jobs.manage');
        });

        // Candidate Directory
        Route::prefix('candidates')->group(function () {
            Route::get('/check-duplicate', [CandidateController::class, 'checkDuplicate'])->middleware('permission:candidates.manage');
            Route::get('/', [CandidateController::class, 'index'])->middleware('permission:candidates.view');
            Route::post('/', [CandidateController::class, 'store'])->middleware('permission:candidates.manage');
            Route::get('/{candidate}', [CandidateController::class, 'show'])->middleware('permission:candidates.view');
            Route::put('/{candidate}', [CandidateController::class, 'update'])->middleware('permission:candidates.manage');
            Route::post('/{candidate}/documents', [CandidateController::class, 'uploadDocument'])->middleware('permission:documents.upload');
        });

        // Applications & Recruitment Pipeline
        Route::prefix('applications')->group(function () {
            Route::get('/pipeline', [ApplicationController::class, 'pipeline'])->middleware('permission:applications.view');
            Route::get('/', [ApplicationController::class, 'index'])->middleware('permission:applications.view');
            Route::post('/', [ApplicationController::class, 'store'])->middleware('permission:applications.submit');
            Route::get('/{application}', [ApplicationController::class, 'show'])->middleware('permission:applications.view');
            Route::patch('/{application}/stage', [ApplicationController::class, 'transitionStage'])->middleware('permission:applications.transition');
            Route::patch('/{application}/recommendation', [ApplicationController::class, 'recommend'])->middleware('permission:applications.recommend');
            Route::get('/{application}/notes', [ApplicationController::class, 'notes'])->middleware('permission:applications.note');
            Route::post('/{application}/notes', [ApplicationController::class, 'storeNote'])->middleware('permission:applications.note');
            Route::delete('/{application}/notes/{note}', [ApplicationController::class, 'destroyNote'])->middleware('permission:applications.note');
        });

        // Interviews & Evaluations
        Route::prefix('interviews')->group(function () {
            Route::get('/', [InterviewController::class, 'index'])->middleware('permission:interviews.view');
            Route::post('/', [InterviewController::class, 'store'])->middleware('permission:interviews.manage');
            Route::get('/{interview}', [InterviewController::class, 'show'])->middleware('permission:interviews.view');
            Route::put('/{interview}', [InterviewController::class, 'update'])->middleware('permission:interviews.manage');
            Route::patch('/{interview}/cancel', [InterviewController::class, 'cancel'])->middleware('permission:interviews.manage');
            Route::patch('/{interview}/complete', [InterviewController::class, 'complete'])->middleware('permission:interviews.manage');
            Route::post('/{interview}/evaluations', [InterviewController::class, 'storeEvaluation'])->middleware('permission:evaluations.submit');
            Route::put('/{interview}/evaluations/{evaluation}', [InterviewController::class, 'updateEvaluation'])->middleware('permission:evaluations.submit');
        });

        // Job Offers
        Route::prefix('job-offers')->group(function () {
            Route::get('/', [JobOfferController::class, 'index'])->middleware('permission:offers.view');
            Route::post('/', [JobOfferController::class, 'store'])->middleware('permission:offers.manage');
            Route::get('/{jobOffer}', [JobOfferController::class, 'show'])->middleware('permission:offers.view');
            Route::put('/{jobOffer}', [JobOfferController::class, 'update'])->middleware('permission:offers.manage');
            Route::patch('/{jobOffer}/approve', [JobOfferController::class, 'approve'])->middleware('permission:offers.approve');
            Route::patch('/{jobOffer}/send', [JobOfferController::class, 'send'])->middleware('permission:offers.manage');
            Route::patch('/{jobOffer}/revoke', [JobOfferController::class, 'revoke'])->middleware('permission:offers.manage');
        });

        // Reference list of departments for internal forms
        Route::get('/departments', [AdminGovernanceController::class, 'departments'])
            ->middleware('permission:departments.view');

        // System Administration & Governance
        Route::prefix('admin')->group(function () {
            Route::get('/users', [AdminGovernanceController::class, 'users'])->middleware('permission:users.view');
            Route::post('/users', [AdminGovernanceController::class, 'storeUser'])->middleware('permission:users.manage');
            Route::put('/users/{user}', [AdminGovernanceController::class, 'updateUser'])->middleware('permission:users.manage');

            Route::get('/roles', [AdminGovernanceController::class, 'roles'])->middleware('permission:roles.manage');
            Route::post('/roles', [AdminGovernanceController::class, 'storeRole'])->middleware('permission:roles.manage');
            Route::put('/roles/{role}/permissions', [AdminGovernanceController::class, 'syncRolePermissions'])->middleware('permission:roles.manage');
            Route::get('/permissions', [AdminGovernanceController::class, 'permissions'])->middleware('permission:roles.manage');

            Route::get('/departments', [AdminGovernanceController::class, 'departments'])->middleware('permission:departments.view');
            Route::post('/departments', [AdminGovernanceController::class, 'storeDepartment'])->middleware('permission:departments.manage');
            Route::put('/departments/{department}', [AdminGovernanceController::class, 'updateDepartment'])->middleware('permission:departments.manage');

            Route::get('/settings', [AdminGovernanceController::class, 'settings'])->middleware('permission:settings.manage');
            Route::put('/settings', [AdminGovernanceController::class, 'updateSettings'])->middleware('permission:settings.manage');

            Route::get('/activity-logs', [AdminGovernanceController::class, 'activityLogs'])->middleware('permission:audit_logs.view');
        });
    });
});
