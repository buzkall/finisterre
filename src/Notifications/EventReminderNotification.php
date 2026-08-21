<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Models\FinisterreEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FinisterreEvent $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->theme('finisterre::themes.finisterre')
            ->subject(__('finisterre::finisterre.events.notifications.reminder_subject', ['title' => $this->event->title]))
            ->greeting(__('finisterre::finisterre.events.notifications.reminder_greeting', ['title' => $this->event->title]))
            ->line(__('finisterre::finisterre.events.notifications.reminder_time', [
                'time' => $this->event->scheduled_start_at?->isoFormat('LLLL'),
            ]))
            ->when(filled($this->event->video_call_url), fn(MailMessage $mail) => $mail
                ->line(__('finisterre::finisterre.events.notifications.scheduled_call', [
                    'url' => $this->event->video_call_url,
                ])))
            ->action(__('finisterre::finisterre.events.notifications.scheduled_cta'), $this->event->publicUrl())
            ->salutation(' ');
    }
}
