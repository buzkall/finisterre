<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\SmsGateway;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The text message about a task that reached one of the priorities in the settings: a new
 * task created with it, or one whose priority changed to it ($from is then the priority it
 * had). It goes to the one configured number, whoever the notifiable is, so it is sent once
 * (see PendingTaskNotifications).
 */
class TaskSmsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FinisterreTask $task, public ?TaskPriorityEnum $from = null) {}

    /**
     * Whether a task with this priority is texted about. Checked before queuing the
     * notification, and again when it is sent, in case the settings changed meanwhile.
     */
    public static function texts(?TaskPriorityEnum $priority): bool
    {
        return $priority instanceof TaskPriorityEnum
            && config('finisterre.sms_notification.enabled') !== false
            && in_array($priority, Typed::array(config('finisterre.sms_notification.notify_priorities')), true);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return [SMSChannel::class];
    }

    public function toSms(object $notifiable): void
    {
        if (! self::texts($this->task->priority)) {
            return;
        }

        $replace = [
            'priority' => $this->task->priority->getLabel(),
            'title'    => $this->task->title,
            'creator'  => $this->task->creatorName()
        ];

        SmsGateway::send(
            $this->task->id . '_' . now()->timestamp,
            $this->from instanceof TaskPriorityEnum ?
                __('finisterre::finisterre.notification.sms_priority_changed', [...$replace, 'from' => $this->from->getLabel()]) :
                __('finisterre::finisterre.notification.sms', $replace)
        );
    }
}
