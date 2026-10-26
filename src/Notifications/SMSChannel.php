<?php

namespace Arzcode\Finisterre\Notifications;

class SMSChannel
{
    public function send(object $notifiable, TaskSmsNotification $notification): void
    {
        $notification->toSMS($notifiable);
    }
}
