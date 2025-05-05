<?php

namespace Buzkall\Finisterre\Notifications;

class SMSChannel
{
    public function send(object $notifiable, TaskNotification $notification): void
    {
        $notification->toSMS($notifiable);
    }
}
