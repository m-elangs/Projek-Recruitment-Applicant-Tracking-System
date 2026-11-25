<?php

namespace App\Console\Commands;

use App\Models\Interview;
use App\Notifications\InterviewLifecycleNotification;
use Illuminate\Console\Command;

class SendUpcomingInterviewReminders extends Command
{
    protected $signature = 'interviews:send-reminders';

    protected $description = 'Send reminder notifications for interviews scheduled within the next 24 hours.';

    public function handle(): int
    {
        $upcoming = Interview::query()
            ->with(['application.candidate.user', 'application.job', 'panelUsers'])
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->whereNull('reminder_sent_at')
            ->whereBetween('scheduled_at', [now(), now()->addHours(24)])
            ->get();

        foreach ($upcoming as $interview) {
            if ($interview->application?->candidate?->user) {
                $interview->application->candidate->user->notify(
                    new InterviewLifecycleNotification($interview, 'scheduled', forCandidate: true)
                );
            }
            foreach ($interview->panelUsers as $panelUser) {
                $panelUser->notify(
                    new InterviewLifecycleNotification($interview, 'scheduled', forCandidate: false)
                );
            }
            $interview->update(['reminder_sent_at' => now()]);
        }

        $this->info("Sent reminders for {$upcoming->count()} interview(s).");

        return self::SUCCESS;
    }
}
