<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStageChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Application $application,
        private readonly string $fromStageName,
        private readonly string $toStageName
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jobTitle = $this->application->job?->title ?? 'Position';

        return (new MailMessage)
            ->subject("Application Status Update — {$jobTitle}")
            ->line("Your application for {$jobTitle} has moved from {$this->fromStageName} to {$this->toStageName}.");
    }

    public function toArray(object $notifiable): array
    {
        $jobTitle = $this->application->job?->title ?? 'Position';

        return [
            'category' => 'application_stage_changed',
            'title' => 'Application Status Updated',
            'message' => "Your application for {$jobTitle} moved to {$this->toStageName}.",
            'application_id' => $this->application->id,
            'from_stage' => $this->fromStageName,
            'to_stage' => $this->toStageName,
            'action_url' => "/portal/applications/{$this->application->id}",
        ];
    }
}
