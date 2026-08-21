<?php

namespace Arzcode\Finisterre\Controllers;

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Models\FinisterreEventAttendee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The event pages served outside Filament: the public page (agenda and open
 * registration) and each attendee's personal page (availability picker,
 * private agenda for logged-in users, video call).
 */
class EventPageController extends Controller
{
    public function show(string $slug): View
    {
        $event = $this->findEvent($slug);

        return view('finisterre::events.public', [
            'event'    => $event,
            'attendee' => $event->attendeeForUser(auth()->user()),
        ]);
    }

    public function attendee(string $slug, string $token): View
    {
        $event = $this->findEvent($slug);

        /** @var FinisterreEventAttendee $attendee */
        $attendee = $event->attendees()->where('token', $token)->firstOrFail();

        return view('finisterre::events.attendee', [
            'event'    => $event,
            'attendee' => $attendee,
        ]);
    }

    public function register(Request $request, string $slug): RedirectResponse
    {
        $event = $this->findEvent($slug);

        abort_unless($event->open_registration, 404);
        abort_if(
            in_array($event->status, [EventStatusEnum::Draft, EventStatusEnum::Completed, EventStatusEnum::Cancelled]),
            404
        );

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $existing = $event->attendees()->where('guest_email', $data['email'])->first();

        if ($existing) {
            // Don't hand the personal link to whoever typed the email; resend
            // it to the inbox instead.
            $existing->sendInvitation();

            return redirect()
                ->to($event->publicUrl())
                ->with('finisterre-registered', __('finisterre::finisterre.events.frontend.registration_resent'));
        }

        $attendee = $event->attendees()->create([
            'guest_name'  => $data['name'],
            'guest_email' => $data['email'],
        ]);

        return redirect()->to($attendee->personalUrl());
    }

    protected function findEvent(string $slug): FinisterreEvent
    {
        abort_unless((bool)config('finisterre.active', false), 404);

        return FinisterreEvent::query()
            ->where('slug', $slug)
            ->whereNot('status', EventStatusEnum::Draft)
            ->firstOrFail();
    }
}
