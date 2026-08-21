<?php

namespace Arzcode\Finisterre\Commands;

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Notifications\EventReminderNotification;
use Illuminate\Console\Command;

class SendEventRemindersCommand extends Command
{
    public $signature = 'finisterre:send-event-reminders';
    public $description = 'Send the configured reminder emails for upcoming scheduled events.';

    public function handle(): int
    {
        $events = FinisterreEvent::query()
            ->where('status', EventStatusEnum::Scheduled)
            ->where('scheduled_start_at', '>', now())
            ->get();

        $sent = 0;

        foreach ($events as $event) {
            foreach ($event->reminderOffsets() as $offset) {
                if (in_array($offset, $event->reminders_sent ?? [])) {
                    continue;
                }

                if (now()->lessThan($event->scheduled_start_at->copy()->subMinutes($offset))) {
                    continue;
                }

                foreach ($event->attendees as $attendee) {
                    $attendee->sendNotification(new EventReminderNotification($event));
                }

                $event->update(['reminders_sent' => [...($event->reminders_sent ?? []), $offset]]);
                $sent++;
            }
        }

        $this->info("Sent {$sent} event reminder(s).");

        return self::SUCCESS;
    }
}
