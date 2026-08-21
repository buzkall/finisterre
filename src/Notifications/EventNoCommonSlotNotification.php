<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Models\FinisterreEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventNoCommonSlotNotification extends Notification implements ShouldQueue
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
            ->subject(__('finisterre::finisterre.events.notifications.no_common_slot_subject', ['title' => $this->event->title]))
            ->greeting(__('finisterre::finisterre.events.notifications.no_common_slot_greeting', ['title' => $this->event->title]))
            ->line(__('finisterre::finisterre.events.notifications.no_common_slot_body'))
            ->action(__('finisterre::finisterre.events.notifications.pending_cta'), $this->event->panelUrl())
            ->salutation(' ');
    }
}
