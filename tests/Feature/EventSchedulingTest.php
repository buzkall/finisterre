<?php

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Models\FinisterreEventAttendee;
use Arzcode\Finisterre\Notifications\EventInvitationNotification;
use Arzcode\Finisterre\Notifications\EventNoCommonSlotNotification;
use Arzcode\Finisterre\Notifications\EventPendingConfirmationNotification;
use Arzcode\Finisterre\Notifications\EventScheduledNotification;
use Arzcode\Finisterre\Support\EventScheduler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Workbench\App\Models\User;

beforeEach(function() {
    config([
        'finisterre.authenticatable'            => User::class,
        'finisterre.authenticatable_table_name' => 'users',
        'finisterre.events.slot_step_minutes'   => 30,
    ]);

    Notification::fake();

    $this->creator = User::factory()->create();
});

function makeSchedulingEvent(array $attributes = []): FinisterreEvent
{
    $event = FinisterreEvent::factory()->scheduling()->create($attributes + [
        'creator_id'       => test()->creator->id,
        'duration_minutes' => 60,
    ]);

    $event->windows()->create([
        'starts_at' => Carbon::parse('2030-01-06 09:00'),
        'ends_at'   => Carbon::parse('2030-01-06 12:00'),
    ]);

    return $event->refresh();
}

it('generates candidate slots inside the windows honoring duration and step', function() {
    $event = makeSchedulingEvent();

    $slots = EventScheduler::for($event)->candidateSlots();

    // 09:00-12:00 window, 60 min duration, 30 min step: 09:00 … 11:00
    expect($slots)->toHaveCount(5)
        ->and($slots->first()->format('H:i'))->toBe('09:00')
        ->and($slots->last()->format('H:i'))->toBe('11:00');
});

it('schedules the earliest slot accepted by every attendee once all have submitted', function() {
    $event = makeSchedulingEvent();

    $one = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);
    $two = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);

    $one->submitAvailability([Carbon::parse('2030-01-06 09:30'), Carbon::parse('2030-01-06 10:30')]);

    expect($event->refresh()->status)->toBe(EventStatusEnum::Scheduling);

    $two->submitAvailability([Carbon::parse('2030-01-06 10:30'), Carbon::parse('2030-01-06 09:30')]);

    $event->refresh();

    expect($event->status)->toBe(EventStatusEnum::Scheduled)
        ->and($event->scheduled_start_at->format('Y-m-d H:i'))->toBe('2030-01-06 09:30')
        ->and($event->scheduled_end_at->format('H:i'))->toBe('10:30');

    Notification::assertSentTo(test()->creator, EventScheduledNotification::class);
    Notification::assertSentOnDemand(EventScheduledNotification::class);
});

it('ignores picks that are not valid candidate slots', function() {
    $event = makeSchedulingEvent();
    $attendee = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);

    $attendee->submitAvailability([
        Carbon::parse('2030-01-06 09:17'), // off-grid
        Carbon::parse('2030-01-06 23:00'), // outside every window
        Carbon::parse('2030-01-06 10:00'), // valid
    ]);

    expect($attendee->slotPicks()->count())->toBe(1)
        ->and($attendee->slotPicks()->first()->starts_at->format('H:i'))->toBe('10:00');
});

it('asks the creator to confirm when the event requires confirmation', function() {
    $event = makeSchedulingEvent(['requires_confirmation' => true]);
    $attendee = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);

    $attendee->submitAvailability([Carbon::parse('2030-01-06 11:00')]);

    $event->refresh();

    expect($event->status)->toBe(EventStatusEnum::PendingConfirmation)
        ->and($event->scheduled_start_at)->toBeNull();

    Notification::assertSentTo(test()->creator, EventPendingConfirmationNotification::class);
});

it('notifies the creator when there is no slot everyone accepts', function() {
    $event = makeSchedulingEvent();

    $one = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);
    $two = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);

    $one->submitAvailability([Carbon::parse('2030-01-06 09:00')]);
    $two->submitAvailability([Carbon::parse('2030-01-06 11:00')]);

    expect($event->refresh()->status)->toBe(EventStatusEnum::Scheduling);

    Notification::assertSentTo(test()->creator, EventNoCommonSlotNotification::class);
});

it('sends invitations when the event opens for scheduling and to late additions', function() {
    $event = FinisterreEvent::factory()->create(['creator_id' => test()->creator->id]);
    $attendee = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);

    // Draft: nothing sent yet.
    Notification::assertNothingSentTo(test()->creator);
    Notification::assertCount(0);
    expect($attendee->refresh()->invited_at)->toBeNull();

    $event->update(['status' => EventStatusEnum::Scheduling]);

    expect($attendee->refresh()->invited_at)->not->toBeNull();
    Notification::assertSentOnDemand(EventInvitationNotification::class);

    $late = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);
    expect($late->refresh()->invited_at)->not->toBeNull();
});

it('generates unique slugs and tokens', function() {
    $first = FinisterreEvent::factory()->create(['title' => 'Kick off']);
    $second = FinisterreEvent::factory()->create(['title' => 'Kick off']);

    expect($first->slug)->toBe('kick-off')
        ->and($second->slug)->toBe('kick-off-2');

    $attendee = FinisterreEventAttendee::factory()->create(['event_id' => $first->id]);
    expect(strlen($attendee->token))->toBe(48);
});

it('notifies a user attendee through their model and skips duplicating the creator', function() {
    $event = makeSchedulingEvent();

    $user = User::factory()->create();
    $userAttendee = FinisterreEventAttendee::factory()->forUser($user->id)->create(['event_id' => $event->id]);
    $creatorAttendee = FinisterreEventAttendee::factory()->forUser(test()->creator->id)->create(['event_id' => $event->id]);

    $slot = [Carbon::parse('2030-01-06 09:00')];
    $userAttendee->submitAvailability($slot);
    $creatorAttendee->submitAvailability($slot);

    expect($event->refresh()->status)->toBe(EventStatusEnum::Scheduled);

    Notification::assertSentTo($user, EventScheduledNotification::class);
    Notification::assertSentTo(
        test()->creator,
        EventScheduledNotification::class,
        fn($notification, $channels, $notifiable) => true
    );
    // The creator got exactly one scheduled notification (not one as creator + one as attendee).
    Notification::assertSentToTimes(test()->creator, EventScheduledNotification::class, 1);
});
