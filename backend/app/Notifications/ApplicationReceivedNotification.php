<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Application $application,
        private readonly bool $forCandidate = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jobTitle = $this->application->job?->title ?? 'Position';

        if ($this->forCandidate) {
            return (new MailMessage)
                ->subject("Application Received — {$jobTitle}")
                ->line("Thank you for applying for {$jobTitle} at TalentFlow.")
                ->line("Your application number is {$this->application->application_number}.")
                ->line('You can track your application progress anytime in the Candidate Portal.');
        }

        $candidateName = $this->application->candidate?->full_name ?? 'A candidate';

        return (new MailMessage)
            ->subject("New Application: {$candidateName} for {$jobTitle}")
            ->line("{$candidateName} submitted a new application ({$this->application->application_number}) for {$jobTitle}.");
    }

    public function toArray(object $notifiable): array
    {
        $jobTitle = $this->application->job?->title ?? 'Position';
        $candidateName = $this->application->candidate?->full_name ?? 'Candidate';

        return [
            'category' => 'application_received',
            'title' => $this->forCandidate
                ? 'Application Submitted'
                : 'New Candidate Application',
            'message' => $this->forCandidate
                ? "Your application ({$this->application->application_number}) for {$jobTitle} has been received."
                : "{$candidateName} applied for {$jobTitle}.",
            'application_id' => $this->application->id,
            'job_id' => $this->application->job_id,
            'action_url' => $this->forCandidate
                ? "/portal/applications/{$this->application->id}"
                : "/applications/{$this->application->id}",
        ];
    }
}
