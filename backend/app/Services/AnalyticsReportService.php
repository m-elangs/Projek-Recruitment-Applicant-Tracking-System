<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\JobRequisition;
use App\Models\RecruitmentStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AnalyticsReportService
{
    public function getDashboardMetrics(User $viewer): array
    {
        $isHM = $viewer->isHiringManager();

        $jobsQuery = Job::query()->when($isHM, fn (Builder $q) => $q->where('hiring_manager_id', $viewer->id));
        $appsQuery = Application::query()->when($isHM, fn (Builder $q) => $q->whereHas('job', fn ($jq) => $jq->where('hiring_manager_id', $viewer->id)));
        $interviewsQuery = Interview::query()->when($isHM, function (Builder $q) use ($viewer) {
            $q->whereHas('application.job', fn ($jq) => $jq->where('hiring_manager_id', $viewer->id))
                ->orWhereHas('interviewerAssignments', fn ($iq) => $iq->where('user_id', $viewer->id));
        });

        $activeJobsCount = (clone $jobsQuery)->where('status', 'published')->count();
        $totalCandidates = $isHM
            ? Candidate::query()->whereHas('applications.job', fn ($q) => $q->where('hiring_manager_id', $viewer->id))->count()
            : Candidate::query()->count();
        $totalApplications = (clone $appsQuery)->count();
        $upcomingInterviews = (clone $interviewsQuery)
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->count();
        $hiredCount = (clone $appsQuery)
            ->whereHas('currentStage', fn ($q) => $q->where('slug', 'hired'))
            ->count();
        $pendingOffers = JobOffer::query()
            ->when($isHM, fn ($q) => $q->whereHas('application.job', fn ($jq) => $jq->where('hiring_manager_id', $viewer->id)))
            ->whereIn('status', ['pending_approval', 'sent'])
            ->count();
        $pendingRequisitions = JobRequisition::query()
            ->when($isHM, fn ($q) => $q->where('requested_by', $viewer->id))
            ->where('status', 'pending_approval')
            ->count();

        $stages = RecruitmentStage::query()->orderBy('display_order')->get();
        $countsByStage = (clone $appsQuery)
            ->selectRaw('current_stage_id, COUNT(*) as aggregate')
            ->groupBy('current_stage_id')
            ->pluck('aggregate', 'current_stage_id');

        $funnel = $stages->map(fn (RecruitmentStage $s) => [
            'slug' => $s->slug,
            'name' => $s->name,
            'is_terminal' => $s->is_terminal,
            'count' => (int) ($countsByStage[$s->id] ?? 0),
            'percentage' => $totalApplications > 0
                ? round(((int) ($countsByStage[$s->id] ?? 0) / $totalApplications) * 100, 1)
                : 0.0,
        ])->values();

        // Monthly Hiring Performance (last 6 months)
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));
        $recentApps = (clone $appsQuery)
            ->where('applied_at', '>=', $months->first())
            ->get(['applied_at', 'hired_at']);

        $hiringPerformance = $months->map(function (Carbon $monthStart) use ($recentApps) {
            $key = $monthStart->format('Y-m');
            $appliedInMonth = $recentApps->filter(fn ($a) => $a->applied_at?->format('Y-m') === $key)->count();
            $hiredInMonth = $recentApps->filter(fn ($a) => $a->hired_at?->format('Y-m') === $key)->count();

            return [
                'month' => $monthStart->format('M Y'),
                'applications' => $appliedInMonth,
                'hired' => $hiredInMonth,
            ];
        })->values();

        // Active Vacancy Fill Progress
        $openVacancies = (clone $jobsQuery)
            ->with('department:id,code,name')
            ->withCount('applications')
            ->whereIn('status', ['published', 'paused'])
            ->orderByDesc('published_at')
            ->limit(6)
            ->get(['id', 'code', 'title', 'department_id', 'headcount', 'hired_count', 'status', 'closes_at']);

        // Recent Activities
        $recentActivities = ActivityLog::query()
            ->with('actor:id,name,email')
            ->when($isHM, fn ($q) => $q->where('actor_user_id', $viewer->id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'actor_user_id', 'actor_role', 'event_type', 'description', 'created_at']);

        // Upcoming Interviews Preview
        $upcomingInterviewsList = (clone $interviewsQuery)
            ->with([
                'application.candidate:id,first_name,last_name',
                'application.job:id,title',
                'panelUsers:id,name',
            ])
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->where('scheduled_at', '>=', now()->subHours(2))
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        return [
            'kpis' => [
                'active_jobs' => $activeJobsCount,
                'total_candidates' => $totalCandidates,
                'total_applications' => $totalApplications,
                'interviews_scheduled' => $upcomingInterviews,
                'hired_candidates' => $hiredCount,
                'pending_offers' => $pendingOffers,
                'pending_requisitions' => $pendingRequisitions,
            ],
            'recruitment_funnel' => $funnel,
            'hiring_performance' => $hiringPerformance,
            'open_vacancies' => $openVacancies,
            'upcoming_interviews' => $upcomingInterviewsList,
            'recent_activities' => $recentActivities,
        ];
    }

    public function getFullAnalyticsReport(array $filters, User $viewer): array
    {
        $departmentId = ! empty($filters['department_id']) ? (int) $filters['department_id'] : null;
        if ($viewer->isHiringManager() && $viewer->department_id && ! $departmentId) {
            $departmentId = $viewer->department_id;
        }

        $appsQuery = Application::query()
            ->with(['currentStage:id,slug,name', 'job:id,code,title,department_id'])
            ->when($departmentId, fn ($q) => $q->whereHas('job', fn ($jq) => $jq->where('department_id', $departmentId)))
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('applied_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('applied_at', '<=', $filters['to']));

        $allApps = $appsQuery->get();
        $totalApps = $allApps->count();

        $hiredApps = $allApps->filter(fn ($a) => $a->currentStage?->slug === 'hired');
        $rejectedApps = $allApps->filter(fn ($a) => $a->currentStage?->slug === 'rejected');
        $withdrawnApps = $allApps->filter(fn ($a) => $a->currentStage?->slug === 'withdrawn');
        $activeApps = $allApps->filter(fn ($a) => $a->is_active);

        $hiredCount = $hiredApps->count();
        $conversionRate = $totalApps > 0 ? round(($hiredCount / $totalApps) * 100, 2) : 0.0;

        $timeToHireDays = $hiredApps
            ->filter(fn ($a) => $a->applied_at && $a->hired_at)
            ->map(fn ($a) => max(0.1, round($a->applied_at->diffInHours($a->hired_at) / 24, 1)));

        $avgTimeToHire = $timeToHireDays->isNotEmpty()
            ? round((float) $timeToHireDays->avg(), 1)
            : 0.0;

        $offersQuery = JobOffer::query()
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));
        $respondedOffers = (clone $offersQuery)->whereIn('status', ['accepted', 'declined'])->count();
        $acceptedOffers = (clone $offersQuery)->where('status', 'accepted')->count();
        $offerAcceptanceRate = $respondedOffers > 0 ? round(($acceptedOffers / $respondedOffers) * 100, 1) : 0.0;

        // 1. Candidates by Stage / Funnel
        $stages = RecruitmentStage::query()->orderBy('display_order')->get();
        $byStage = $stages->map(function (RecruitmentStage $s) use ($allApps, $totalApps) {
            $count = $allApps->where('current_stage_id', $s->id)->count();

            return [
                'slug' => $s->slug,
                'name' => $s->name,
                'is_terminal' => $s->is_terminal,
                'count' => $count,
                'share_percentage' => $totalApps > 0 ? round(($count / $totalApps) * 100, 1) : 0.0,
            ];
        })->values();

        // 2. Applications by Job
        $jobs = Job::query()
            ->with('department:id,code,name')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $appsByJob = $jobs->map(function (Job $job) use ($allApps) {
            $jobApps = $allApps->where('job_id', $job->id);

            return [
                'job_id' => $job->id,
                'code' => $job->code,
                'title' => $job->title,
                'department' => $job->department?->name,
                'status' => $job->status,
                'headcount' => $job->headcount,
                'total_applications' => $jobApps->count(),
                'active' => $jobApps->where('is_active', true)->count(),
                'hired' => $jobApps->filter(fn ($a) => $a->currentStage?->slug === 'hired')->count(),
                'rejected' => $jobApps->filter(fn ($a) => $a->currentStage?->slug === 'rejected')->count(),
            ];
        })->values();

        // 3. Candidate Sources Breakdown
        $sources = ['careers_portal', 'linkedin', 'referral', 'agency', 'direct_sourced', 'other'];
        $sourceBreakdown = collect($sources)->map(function (string $source) use ($allApps) {
            $group = $allApps->where('source', $source);
            $total = $group->count();
            $hired = $group->filter(fn ($a) => $a->currentStage?->slug === 'hired')->count();

            return [
                'source' => $source,
                'label' => ucwords(str_replace('_', ' ', $source)),
                'applications' => $total,
                'hired' => $hired,
                'conversion_rate' => $total > 0 ? round(($hired / $total) * 100, 1) : 0.0,
            ];
        })->filter(fn ($row) => $row['applications'] > 0)->values();

        // 4. Department Performance
        $departments = Department::query()
            ->when($departmentId, fn ($q) => $q->where('id', $departmentId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $departmentPerformance = $departments->map(function (Department $dept) use ($allApps) {
            $deptApps = $allApps->filter(fn ($a) => $a->job?->department_id === $dept->id);
            $deptHired = $deptApps->filter(fn ($a) => $a->currentStage?->slug === 'hired');
            $tth = $deptHired
                ->filter(fn ($a) => $a->applied_at && $a->hired_at)
                ->map(fn ($a) => max(0.1, round($a->applied_at->diffInHours($a->hired_at) / 24, 1)));

            return [
                'department_id' => $dept->id,
                'code' => $dept->code,
                'department' => $dept->name,
                'open_jobs' => Job::query()->where('department_id', $dept->id)->where('status', 'published')->count(),
                'applications' => $deptApps->count(),
                'hired' => $deptHired->count(),
                'rejected' => $deptApps->filter(fn ($a) => $a->currentStage?->slug === 'rejected')->count(),
                'avg_time_to_hire_days' => $tth->isNotEmpty() ? round((float) $tth->avg(), 1) : 0.0,
            ];
        })->values();

        return [
            'overview' => [
                'total_applications' => $totalApps,
                'active_applications' => $activeApps->count(),
                'hired_candidates' => $hiredCount,
                'rejected_candidates' => $rejectedApps->count(),
                'withdrawn_candidates' => $withdrawnApps->count(),
                'hiring_conversion_rate' => $conversionRate,
                'avg_time_to_hire_days' => $avgTimeToHire,
                'offer_acceptance_rate' => $offerAcceptanceRate,
            ],
            'candidates_by_stage' => $byStage,
            'applications_by_job' => $appsByJob,
            'candidate_sources' => $sourceBreakdown,
            'department_performance' => $departmentPerformance,
        ];
    }
}
