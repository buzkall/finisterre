<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\SmsGateway;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The text message about a new task with one of the priorities in the settings. It goes
 * to the one configured number, whoever the notifiable is, so it is sent once, with the
 * assignee's notification (see PendingTaskNotifications).
 */
class TaskSmsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FinisterreTask $task) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return [SMSChannel::class];
    }

    public function toSms(object $notifiable): void
    {
        if (config('finisterre.sms_notification.enabled') === false) {
            return;
        }

        if (! in_array($this->task->priority, Typed::array(config('finisterre.sms_notification.notify_priorities')))) {
            return;
        }

        SmsGateway::send(
            $this->task->id . '_' . now()->timestamp,
            __('finisterre::finisterre.notification.sms', [
                'priority' => $this->task->priority->getLabel(),
                'title'    => $this->task->title,
                'creator'  => $this->task->creatorName(),
            ])
        );
    }
}
