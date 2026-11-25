<?php

namespace App\Services;

use App\Enums\StageSlug;
use App\Exceptions\BusinessRuleException;
use App\Models\Application;
use App\Models\Interview;
use App\Models\InterviewEvaluation;
use App\Models\User;
use App\Notifications\InterviewLifecycleNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InterviewService
{
    public function __construct(
        private readonly ApplicationPipelineService $pipelineService,
        private readonly AuditLogService $auditLog
    ) {}

    public function paginate(array $filters, User $viewer): LengthAwarePaginator
    {
        $query = Interview::query()
            ->with([
                'application.job:id,code,title,department_id,hiring_manager_id',
                'application.candidate:id,user_id,first_name,last_name,email,phone',
                'application.currentStage:id,slug,name',
                'panelUsers:id,name,email,job_title',
                'scheduler:id,name',
            ]);

        if (! $viewer->isCandidate()) {
            $query->with('evaluations.interviewer:id,name,job_title');
        }

        if ($viewer->isCandidate()) {
            $query->whereHas('application.candidate', fn ($q) => $q->where('user_id', $viewer->id));
        } elseif ($viewer->isHiringManager()) {
            $query->where(function ($q) use ($viewer) {
                $q->whereHas('application.job', fn ($jq) => $jq->where('hiring_manager_id', $viewer->id))
                    ->orWhereHas('interviewerAssignments', fn ($iq) => $iq->where('user_id', $viewer->id));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['application_id'])) {
            $query->where('application_id', (int) $filters['application_id']);
        }

        if (! empty($filters['job_id'])) {
            $jobId = (int) $filters['job_id'];
            $query->whereHas('application', fn ($q) => $q->where('job_id', $jobId));
        }

        if (! empty($filters['from'])) {
            $query->whereDate('scheduled_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('scheduled_at', '<=', $filters['to']);
        }

        return $query->orderBy('scheduled_at')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    public function schedule(array $data, User $actor): Interview
    {
        $application = Application::query()
            ->with(['currentStage', 'job', 'candidate.user'])
            ->findOrFail($data['application_id']);

        if (! $application->is_active || $application->currentStage?->is_terminal) {
            throw new BusinessRuleException(
                'APPLICATION_INACTIVE',
                'Interviews can only be scheduled for active job applications.',
                422
            );
        }

        return DB::transaction(function () use ($application, $data, $actor) {
            $nextRound = (int) $application->interviews()->max('round_number') + 1;

            $interview = Interview::query()->create([
                'application_id' => $application->id,
                'scheduled_by' => $actor->id,
                'title' => $data['title'],
                'round_number' => $data['round_number'] ?? $nextRound,
                'interview_type' => $data['interview_type'] ?? 'technical',
                'mode' => $data['mode'],
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'] ?? 60,
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
                'meeting_url' => $data['mode'] === 'online' ? ($data['meeting_url'] ?? null) : null,
                'location' => $data['mode'] === 'onsite' ? ($data['location'] ?? null) : null,
                'candidate_instructions' => $data['candidate_instructions'] ?? null,
                'internal_agenda' => $data['internal_agenda'] ?? null,
                'status' => 'scheduled',
            ]);

            $this->syncInterviewers(
                $interview,
                $data['interviewer_ids'] ?? [],
                isset($data['lead_interviewer_id']) ? (int) $data['lead_interviewer_id'] : null
            );

            // Automatically advance early-stage applications to Interview stage when allowed
            if (in_array($application->currentStage->slug, ['screening', 'shortlisted'], true) && $actor->hasPermission('applications.transition')) {
                $this->pipelineService->transitionStage(
                    $application,
                    StageSlug::INTERVIEW->value,
                    $actor,
                    reason: 'Interview scheduled'
                );
            }

            $this->auditLog->log(
                eventType: 'interview.scheduled',
                auditable: $interview,
                description: "Interview '{$interview->title}' scheduled for {$application->application_number}",
                actor: $actor,
                newValues: ['scheduled_at' => $interview->scheduled_at?->toIso8601String(), 'mode' => $interview->mode]
            );

            $interview->load(['application.job', 'application.candidate.user', 'panelUsers']);
            $this->notifyParticipants($interview, 'scheduled');

            return $interview;
        });
    }

    public function update(Interview $interview, array $data, User $actor): Interview
    {
        if ($interview->status === 'cancelled') {
            throw new BusinessRuleException('INTERVIEW_CANCELLED', 'Cancelled interviews cannot be modified.', 422);
        }

        return DB::transaction(function () use ($interview, $data, $actor) {
            $oldScheduled = $interview->scheduled_at?->toIso8601String();
            $oldMode = $interview->mode;

            $interviewerIds = $data['interviewer_ids'] ?? null;
            $leadId = isset($data['lead_interviewer_id']) ? (int) $data['lead_interviewer_id'] : null;
            unset($data['interviewer_ids'], $data['lead_interviewer_id']);

            $rescheduled = isset($data['scheduled_at'])
                && $interview->scheduled_at?->format('Y-m-d H:i') !== date('Y-m-d H:i', strtotime($data['scheduled_at']));

            if ($rescheduled && in_array($interview->status, ['scheduled', 'rescheduled'], true)) {
                $data['status'] = 'rescheduled';
            }

            $interview->update($data);

            if (is_array($interviewerIds)) {
                $this->syncInterviewers($interview, $interviewerIds, $leadId);
            }

            $event = $rescheduled ? 'rescheduled' : 'updated';

            $this->auditLog->log(
                eventType: "interview.{$event}",
                auditable: $interview,
                description: "Interview '{$interview->title}' {$event}",
                actor: $actor,
                oldValues: ['scheduled_at' => $oldScheduled, 'mode' => $oldMode],
                newValues: ['scheduled_at' => $interview->scheduled_at?->toIso8601String(), 'mode' => $interview->mode]
            );

            $interview->load(['application.job', 'application.candidate.user', 'panelUsers', 'evaluations.interviewer']);

            if ($rescheduled) {
                $this->notifyParticipants($interview, 'rescheduled');
            }

            return $interview;
        });
    }

    public function cancel(Interview $interview, string $reason, User $actor): Interview
    {
        if ($interview->status === 'cancelled') {
            throw new BusinessRuleException('ALREADY_CANCELLED', 'Interview is already cancelled.', 422);
        }

        return DB::transaction(function () use ($interview, $reason, $actor) {
            $interview->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
            ]);

            $this->auditLog->log(
                eventType: 'interview.cancelled',
                auditable: $interview,
                description: "Interview '{$interview->title}' cancelled: {$reason}",
                actor: $actor
            );

            $interview->load(['application.job', 'application.candidate.user', 'panelUsers']);
            $this->notifyParticipants($interview, 'cancelled');

            return $interview;
        });
    }

    public function markCompleted(Interview $interview, User $actor): Interview
    {
        if ($interview->status === 'cancelled') {
            throw new BusinessRuleException('INTERVIEW_CANCELLED', 'Cannot complete a cancelled interview.', 422);
        }

        $interview->update(['status' => 'completed']);

        $this->auditLog->log(
            eventType: 'interview.completed',
            auditable: $interview,
            description: "Interview '{$interview->title}' marked completed",
            actor: $actor
        );

        return $interview->fresh(['application.job', 'application.candidate', 'panelUsers', 'evaluations.interviewer']);
    }

    public function submitEvaluation(Interview $interview, array $data, User $interviewer): InterviewEvaluation
    {
        if ($interview->status === 'cancelled') {
            throw new BusinessRuleException(
                'INTERVIEW_CANCELLED',
                'Evaluations cannot be submitted for cancelled interviews.',
                422
            );
        }

        return DB::transaction(function () use ($interview, $data, $interviewer) {
            $existing = InterviewEvaluation::query()
                ->where('interview_id', $interview->id)
                ->where('interviewer_user_id', $interviewer->id)
                ->first();

            if ($existing) {
                throw new BusinessRuleException(
                    'EVALUATION_ALREADY_SUBMITTED',
                    'You have already submitted an evaluation for this interview.',
                    409
                );
            }

            $evaluation = InterviewEvaluation::query()->create([
                'interview_id' => $interview->id,
                'interviewer_user_id' => $interviewer->id,
                'technical_skills_rating' => $data['technical_skills_rating'],
                'communication_rating' => $data['communication_rating'],
                'problem_solving_rating' => $data['problem_solving_rating'],
                'relevant_experience_rating' => $data['relevant_experience_rating'],
                'cultural_fit_rating' => $data['cultural_fit_rating'],
                'interview_notes' => $data['interview_notes'] ?? null,
                'strengths' => $data['strengths'],
                'weaknesses' => $data['weaknesses'],
                'overall_feedback' => $data['overall_feedback'],
                'recommendation' => $data['recommendation'],
                'submitted_at' => now(),
            ]);

            if ($interview->status === 'scheduled' || $interview->status === 'rescheduled') {
                $assignedCount = $interview->interviewerAssignments()->count();
                $submittedCount = $interview->evaluations()->count();
                if ($assignedCount > 0 && $submittedCount >= $assignedCount) {
                    $interview->update(['status' => 'completed']);
                }
            }

            $this->auditLog->log(
                eventType: 'interview.evaluated',
                auditable: $evaluation,
                description: "Evaluation submitted by {$interviewer->name} for interview '{$interview->title}' ({$evaluation->recommendation})",
                actor: $interviewer,
                newValues: ['recommendation' => $evaluation->recommendation, 'average_rating' => $evaluation->average_rating]
            );

            return $evaluation->fresh('interviewer:id,name,email,job_title');
        });
    }

    public function updateEvaluation(InterviewEvaluation $evaluation, array $data, User $actor): InterviewEvaluation
    {
        if (! $actor->isAdmin() && $evaluation->interviewer_user_id !== $actor->id) {
            throw new BusinessRuleException('FORBIDDEN', 'You can only update your own interview evaluation.', 403);
        }

        $evaluation->update($data);

        $this->auditLog->log(
            eventType: 'interview.evaluation_updated',
            auditable: $evaluation,
            description: "Evaluation updated by {$actor->name}",
            actor: $actor
        );

        return $evaluation->fresh('interviewer:id,name,email,job_title');
    }

    private function syncInterviewers(Interview $interview, array $userIds, ?int $leadId): void
    {
        $uniqueIds = array_values(array_unique(array_map('intval', $userIds)));
        $interview->interviewerAssignments()->delete();

        $now = now();
        foreach ($uniqueIds as $index => $uid) {
            $interview->interviewerAssignments()->create([
                'user_id' => $uid,
                'is_lead' => $leadId ? ($uid === $leadId) : ($index === 0),
                'rsvp_status' => 'accepted',
                'assigned_at' => $now,
            ]);
        }
    }

    private function notifyParticipants(Interview $interview, string $event): void
    {
        $candidateUser = $interview->application?->candidate?->user;
        if ($candidateUser) {
            $candidateUser->notify(new InterviewLifecycleNotification($interview, $event, forCandidate: true));
        }

        foreach ($interview->panelUsers as $panelUser) {
            $panelUser->notify(new InterviewLifecycleNotification($interview, $event, forCandidate: false));
        }
    }
}
