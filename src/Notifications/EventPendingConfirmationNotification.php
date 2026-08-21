<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Models\FinisterreEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class EventPendingConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<Carbon>  $commonSlots */
    public function __construct(public FinisterreEvent $event, public array $commonSlots = []) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $slots = collect($this->commonSlots)
            ->map(fn(Carbon $slot) => $slot->isoFormat('LLLL'))
            ->implode("\n");

        return (new MailMessage)
            ->theme('finisterre::themes.finisterre')
            ->subject(__('finisterre::finisterre.events.notifications.pending_subject', ['title' => $this->event->title]))
            ->greeting(__('finisterre::finisterre.events.notifications.pending_greeting', ['title' => $this->event->title]))
            ->line(__('finisterre::finisterre.events.notifications.pending_body'))
            ->when($slots !== '', fn(MailMessage $mail) => $mail->line($slots))
            ->action(__('finisterre::finisterre.events.notifications.pending_cta'), $this->event->panelUrl())
            ->salutation(' ');
    }
}
