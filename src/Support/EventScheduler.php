<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Notifications\EventNoCommonSlotNotification;
use Arzcode\Finisterre\Notifications\EventPendingConfirmationNotification;
use Arzcode\Finisterre\Notifications\EventScheduledNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Generates the candidate meeting slots for an event and resolves the final
 * time once every attendee has submitted their availability.
 */
class EventScheduler
{
    public function __construct(protected FinisterreEvent $event) {}

    public static function for(FinisterreEvent $event): self
    {
        return new self($event);
    }

    /**
     * All candidate slot start times: one every slot_step_minutes inside each
     * suggested window, where a frame of the event's estimated duration still
     * fits in the window.
     *
     * @return Collection<int, Carbon>
     */
    public function candidateSlots(): Collection
    {
        $step = max(5, (int)config('finisterre.events.slot_step_minutes', 30));
        $duration = max(1, $this->event->duration_minutes);

        $slots = collect();

        foreach ($this->event->windows as $window) {
            $start = $window->starts_at->copy();

            while ($start->copy()->addMinutes($duration)->lessThanOrEqualTo($window->ends_at)) {
                $slots->push($start->copy());
                $start = $start->copy()->addMinutes($step);
            }
        }

        return $slots
            ->unique(fn(Carbon $slot) => $slot->getTimestamp())
            ->sortBy(fn(Carbon $slot) => $slot->getTimestamp())
            ->values();
    }

    /**
     * Slot start times every attendee marked themselves available for,
     * earliest first.
     *
     * @return Collection<int, Carbon>
     */
    public function commonSlots(): Collection
    {
        $attendeeIds = $this->event->attendees()->pluck('id');

        if ($attendeeIds->isEmpty()) {
            return collect();
        }

        return $this->event->slotPicks()
            ->whereIn('attendee_id', $attendeeIds)
            ->get()
            ->groupBy(fn($pick) => $pick->starts_at->getTimestamp())
            ->filter(fn($picks) => $picks->pluck('attendee_id')->unique()->count() === $attendeeIds->count())
            ->map(fn($picks) => $picks->first()->starts_at)
            ->sortBy(fn(Carbon $slot) => $slot->getTimestamp())
            ->values();
    }

    /**
     * Called each time an attendee submits availability. Once everyone has
     * answered, the earliest slot accepted by all wins: it is either applied
     * directly or, when the event requires it, handed to the creator to
     * confirm. With no common slot, the creator is alerted instead.
     */
    public function evaluate(): void
    {
        if (! $this->event->status->acceptsAvailability()) {
            return;
        }

        if (! $this->event->allAvailabilitySubmitted()) {
            return;
        }

        $common = $this->commonSlots();

        if ($common->isEmpty()) {
            $this->event->creator?->notify(new EventNoCommonSlotNotification($this->event)); // @phpstan-ignore-line method.notFound

            return;
        }

        if ($this->event->requires_confirmation) {
            if ($this->event->status !== EventStatusEnum::PendingConfirmation) {
                $this->event->update(['status' => EventStatusEnum::PendingConfirmation]);
            }

            $this->event->creator?->notify(new EventPendingConfirmationNotification($this->event, $common->all())); // @phpstan-ignore-line method.notFound

            return;
        }

        $this->schedule($common->first());
    }

    /**
     * Lock the final time, provision the video call, and notify everyone.
     */
    public function schedule(Carbon $start): void
    {
        $this->event->fill([
            'scheduled_start_at' => $start,
            'scheduled_end_at'   => $start->copy()->addMinutes($this->event->duration_minutes),
            'status'             => EventStatusEnum::Scheduled,
        ]);

        if (blank($this->event->video_call_url)) {
            app(WherebyService::class)->assignVideoCall($this->event);
        }

        $this->event->save();

        $this->event->creator?->notify(new EventScheduledNotification($this->event)); // @phpstan-ignore-line method.notFound

        foreach ($this->event->attendees as $attendee) {
            // The creator may also be an attendee; don't notify them twice.
            if (! $attendee->isGuest() && $attendee->user_id === $this->event->creator_id) {
                continue;
            }

            $attendee->sendNotification(new EventScheduledNotification($this->event));
        }
    }
}
