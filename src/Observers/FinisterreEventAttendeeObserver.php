<?php

namespace Arzcode\Finisterre\Observers;

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Models\FinisterreEventAttendee;
use Illuminate\Support\Str;

class FinisterreEventAttendeeObserver
{
    public function creating(FinisterreEventAttendee $attendee): void
    {
        if (blank($attendee->token)) {
            $attendee->token = Str::random(48);
        }
    }

    public function created(FinisterreEventAttendee $attendee): void
    {
        // While the event is a draft, invitations wait until scheduling opens
        // (see FinisterreEventObserver::updated). Anyone added later is
        // invited right away.
        if ($attendee->event->status !== EventStatusEnum::Draft) {
            $attendee->sendInvitation();
        }
    }
}
