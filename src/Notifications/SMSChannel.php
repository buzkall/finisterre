<?php

namespace Buzkall\Finisterre\Notifications;

use Illuminate\Notifications\Notification;

class SMSChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toSMS($notifiable);
    }
}
