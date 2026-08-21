<?php

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Models\FinisterreEventAttendee;
use Arzcode\Finisterre\Notifications\EventReminderNotification;
use Illuminate\Support\Facades\Notification;
use Workbench\App\Models\User;

beforeEach(function() {
    config([
        'finisterre.authenticatable'            => User::class,
        'finisterre.authenticatable_table_name' => 'users',
    ]);

    Notification::fake();
});

function makeScheduledEvent(array $attributes = []): FinisterreEvent
{
    $event = FinisterreEvent::factory()->create($attributes + [
        'status'             => EventStatusEnum::Scheduled,
        'creator_id'         => User::factory()->create()->id,
        'duration_minutes'   => 60,
        'scheduled_start_at' => now()->addMinutes(30),
        'scheduled_end_at'   => now()->addMinutes(90),
    ]);

    FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);

    return $event->refresh();
}

it('sends due reminders once and skips future ones', function() {
    $event = makeScheduledEvent(['reminder_offsets' => [60, 15]]);

    // 30 minutes out: the 60-minute reminder is due, the 15-minute one is not.
    $this->artisan('finisterre:send-event-reminders')->assertSuccessful();

    Notification::assertSentOnDemandTimes(EventReminderNotification::class, 1);
    expect($event->refresh()->reminders_sent)->toBe([60]);

    // Running again sends nothing new.
    $this->artisan('finisterre:send-event-reminders')->assertSuccessful();
    Notification::assertSentOnDemandTimes(EventReminderNotification::class, 1);

    // Once inside the 15-minute window, the second reminder goes out.
    $this->travel(20)->minutes();
    $this->artisan('finisterre:send-event-reminders')->assertSuccessful();

    Notification::assertSentOnDemandTimes(EventReminderNotification::class, 2);
    expect($event->refresh()->reminders_sent)->toBe([60, 15]);
});

it('ignores events that are not scheduled or already started', function() {
    makeScheduledEvent([
        'reminder_offsets'   => [60],
        'scheduled_start_at' => now()->subMinutes(5),
        'scheduled_end_at'   => now()->addMinutes(55),
    ]);

    $collecting = FinisterreEvent::factory()->scheduling()->create([
        'creator_id'       => User::factory()->create()->id,
        'reminder_offsets' => [60],
    ]);
    FinisterreEventAttendee::factory()->create(['event_id' => $collecting->id]);

    $this->artisan('finisterre:send-event-reminders')->assertSuccessful();

    Notification::assertSentOnDemandTimes(EventReminderNotification::class, 0);
});
