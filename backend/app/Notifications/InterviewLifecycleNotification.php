<?php

namespace App\Notifications;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterviewLifecycleNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Interview $interview,
        private readonly string $event, // scheduled | rescheduled | cancelled
        private readonly bool $forCandidate = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jobTitle = $this->interview->application?->job?->title ?? 'Position';
        $when = $this->interview->scheduled_at?->format('Y-m-d H:i').' '.$this->interview->timezone;
        $label = ucfirst($this->event);

        $mail = (new MailMessage)
            ->subject("Interview {$label} — {$jobTitle}")
            ->line("Interview '{$this->interview->title}' for {$jobTitle} has been {$this->event}.")
            ->line("Scheduled Date & Time: {$when} ({$this->interview->duration_minutes} mins)");

        if ($this->event !== 'cancelled') {
            $where = $this->interview->mode === 'online'
                ? "Online Meeting: {$this->interview->meeting_url}"
                : "Onsite Location: {$this->interview->location}";
            $mail->line($where);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        $jobTitle = $this->interview->application?->job?->title ?? 'Position';
        $when = $this->interview->scheduled_at?->format('d M Y, H:i');

        $titles = [
            'scheduled' => 'Interview Scheduled',
            'rescheduled' => 'Interview Rescheduled',
            'cancelled' => 'Interview Cancelled',
        ];

        return [
            'category' => "interview_{$this->event}",
            'title' => $titles[$this->event] ?? 'Interview Update',
            'message' => "{$this->interview->title} for {$jobTitle} ({$when}) was {$this->event}.",
            'interview_id' => $this->interview->id,
            'application_id' => $this->interview->application_id,
            'action_url' => $this->forCandidate ? '/portal/interviews' : "/interviews/{$this->interview->id}",
        ];
    }
}
