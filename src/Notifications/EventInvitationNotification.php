<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Models\FinisterreEventAttendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FinisterreEvent $event, public FinisterreEventAttendee $attendee) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $windows = $this->event->windows
            ->map(fn($window) => $window->starts_at->isoFormat('LLLL') . ' – ' . $window->ends_at->isoFormat('LT'))
            ->implode("\n");

        return (new MailMessage)
            ->theme('finisterre::themes.finisterre')
            ->subject(__('finisterre::finisterre.events.notifications.invitation_subject', ['title' => $this->event->title]))
            ->greeting(__('finisterre::finisterre.events.notifications.invitation_greeting', [
                'name'  => $this->attendee->displayName(),
                'title' => $this->event->title,
            ]))
            ->when(filled($this->event->description), fn(MailMessage $mail) => $mail->line(strip_tags((string)$this->event->description)))
            ->line(__('finisterre::finisterre.events.notifications.invitation_duration', [
                'duration' => $this->event->duration_minutes,
            ]))
            ->when($windows !== '', fn(MailMessage $mail) => $mail
                ->line(__('finisterre::finisterre.events.notifications.invitation_windows'))
                ->line($windows))
            ->line(__('finisterre::finisterre.events.notifications.invitation_cta_hint'))
            ->action(__('finisterre::finisterre.events.notifications.invitation_cta'), $this->attendee->personalUrl())
            ->salutation(' ');
    }
}
