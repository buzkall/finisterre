<?php

namespace Arzcode\Finisterre\Filament\Livewire;

use Arzcode\Finisterre\Models\FinisterreEventAttendee;
use Arzcode\Finisterre\Support\EventScheduler;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Mobile-first slot picker for the attendee's personal event page: the
 * candidate frames (of the event's estimated duration) are listed per day and
 * toggled with a tap; saving submits the availability and re-evaluates the
 * event schedule.
 */
class AvailabilityPicker extends Component
{
    public FinisterreEventAttendee $attendee;

    /** @var list<string> Selected slot starts as "Y-m-d H:i:s". */
    public array $selected = [];

    public bool $saved = false;

    public function mount(): void
    {
        $this->selected = $this->attendee->slotPicks
            ->map(fn($pick) => $pick->starts_at->toDateTimeString())
            ->all();
        $this->saved = $this->attendee->availability_submitted_at !== null;
    }

    public function toggle(string $slot): void
    {
        if (! $this->attendee->event->status->acceptsAvailability()) {
            return;
        }

        $valid = EventScheduler::for($this->attendee->event)->candidateSlots()
            ->map(fn(Carbon $candidate) => $candidate->toDateTimeString());

        if (! $valid->contains($slot)) {
            return;
        }

        $this->selected = in_array($slot, $this->selected)
            ? array_values(array_diff($this->selected, [$slot]))
            : [...$this->selected, $slot];

        $this->saved = false;
    }

    public function save(): void
    {
        if (! $this->attendee->event->status->acceptsAvailability()) {
            return;
        }

        $this->attendee->submitAvailability($this->selected);
        $this->attendee->refresh();
        $this->saved = true;
    }

    /**
     * Candidate slots grouped by day, each with its acceptance count so
     * attendees can see which frames are popular.
     */
    public function slotsByDay(): Collection
    {
        $event = $this->attendee->event;

        $counts = $event->slotPicks()->get()
            ->groupBy(fn($pick) => $pick->starts_at->toDateTimeString())
            ->map(fn($picks) => $picks->pluck('attendee_id')->unique()->count());

        return EventScheduler::for($event)->candidateSlots()
            ->map(fn(Carbon $slot) => [
                'value' => $slot->toDateTimeString(),
                'label' => $slot->isoFormat('HH:mm'),
                'day'   => $slot->isoFormat('dddd D MMMM'),
                'count' => $counts[$slot->toDateTimeString()] ?? 0,
            ])
            ->groupBy('day');
    }

    public function render(): View
    {
        return view('finisterre::events.availability-picker', [
            'event'      => $this->attendee->event->refresh(),
            'slotsByDay' => $this->slotsByDay(),
            'total'      => $this->attendee->event->attendees()->count(),
        ]);
    }
}
