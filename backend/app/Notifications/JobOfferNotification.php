<?php

namespace App\Notifications;

use App\Models\JobOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobOfferNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly JobOffer $offer,
        private readonly string $event, // sent | accepted | declined
        private readonly bool $forCandidate = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->event === 'sent') {
            return (new MailMessage)
                ->subject("Job Offer — {$this->offer->position_title}")
                ->line("Congratulations! We have extended an official job offer ({$this->offer->offer_number}) for {$this->offer->position_title}.")
                ->line("Please review and respond before {$this->offer->expires_at?->format('Y-m-d H:i')}.");
        }

        $candidateName = $this->offer->application?->candidate?->full_name ?? 'Candidate';

        return (new MailMessage)
            ->subject("Job Offer ".ucfirst($this->event)." — {$this->offer->position_title}")
            ->line("{$candidateName} has {$this->event} job offer {$this->offer->offer_number} for {$this->offer->position_title}.");
    }

    public function toArray(object $notifiable): array
    {
        $candidateName = $this->offer->application?->candidate?->full_name ?? 'Candidate';

        if ($this->event === 'sent') {
            return [
                'category' => 'offer_sent',
                'title' => 'Official Job Offer Received',
                'message' => "You have received a job offer for {$this->offer->position_title}.",
                'offer_id' => $this->offer->id,
                'application_id' => $this->offer->application_id,
                'action_url' => "/portal/applications/{$this->offer->application_id}",
            ];
        }

        return [
            'category' => "offer_{$this->event}",
            'title' => 'Job Offer '.ucfirst($this->event),
            'message' => "{$candidateName} {$this->event} offer {$this->offer->offer_number} ({$this->offer->position_title}).",
            'offer_id' => $this->offer->id,
            'application_id' => $this->offer->application_id,
            'action_url' => '/job-offers',
        ];
    }
}
