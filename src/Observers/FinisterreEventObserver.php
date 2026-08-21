<?php

namespace Arzcode\Finisterre\Observers;

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Illuminate\Support\Str;

class FinisterreEventObserver
{
    public function creating(FinisterreEvent $event): void
    {
        $event->status = $event->status ?? EventStatusEnum::Draft;
        $event->creator_id = $event->creator_id ?? auth()->id();
        $event->duration_minutes = $event->duration_minutes
            ?: (int)config('finisterre.events.default_duration_minutes', 60);

        if (blank($event->slug)) {
            $event->slug = $this->uniqueSlug($event->title);
        }
    }

    public function updated(FinisterreEvent $event): void
    {
        // Opening the scheduling phase sends the pending invitations.
        if ($event->wasChanged('status') && $event->status === EventStatusEnum::Scheduling) {
            foreach ($event->attendees()->whereNull('invited_at')->get() as $attendee) {
                $attendee->sendInvitation();
            }
        }
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $suffix = 2;

        while (FinisterreEvent::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
