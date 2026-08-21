<?php

use Arzcode\Finisterre\Filament\Livewire\AvailabilityPicker;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Models\FinisterreEventAttendee;
use Arzcode\Finisterre\Notifications\EventInvitationNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    config([
        'finisterre.authenticatable'            => User::class,
        'finisterre.authenticatable_table_name' => 'users',
        'finisterre.active'                     => true,
    ]);

    Notification::fake();
});

function makeFrontendEvent(array $attributes = []): FinisterreEvent
{
    $event = FinisterreEvent::factory()->scheduling()->create($attributes + [
        'creator_id'       => User::factory()->create()->id,
        'duration_minutes' => 60,
    ]);

    $event->windows()->create([
        'starts_at' => Carbon::parse('2030-01-06 09:00'),
        'ends_at'   => Carbon::parse('2030-01-06 12:00'),
    ]);

    return $event->refresh();
}

it('serves the public event page with the public agenda only', function() {
    $event = makeFrontendEvent([
        'public_agenda'  => 'The public agenda body',
        'private_agenda' => 'The private agenda body',
    ]);

    $this->get('/events/' . $event->slug)
        ->assertOk()
        ->assertSee($event->title)
        ->assertSee('The public agenda body')
        ->assertDontSee('The private agenda body');
});

it('hides draft events and everything when the package is inactive', function() {
    $draft = FinisterreEvent::factory()->create();
    $this->get('/events/' . $draft->slug)->assertNotFound();

    $event = makeFrontendEvent();
    config(['finisterre.active' => false]);
    $this->get('/events/' . $event->slug)->assertNotFound();
});

it('serves the personal attendee page by token and rejects bad tokens', function() {
    $event = makeFrontendEvent();
    $attendee = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);

    $this->get('/events/' . $event->slug . '/a/' . $attendee->token)
        ->assertOk()
        ->assertSee($attendee->displayName());

    $this->get('/events/' . $event->slug . '/a/wrong-token')->assertNotFound();
});

it('shows the private agenda on the attendee page only to logged-in users', function() {
    $event = makeFrontendEvent(['private_agenda' => 'The private agenda body']);
    $attendee = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);

    $this->get('/events/' . $event->slug . '/a/' . $attendee->token)
        ->assertDontSee('The private agenda body');

    $this->actingAs(User::factory()->create())
        ->get('/events/' . $event->slug . '/a/' . $attendee->token)
        ->assertSee('The private agenda body');
});

it('registers a guest through the public page and redirects to their personal page', function() {
    $event = makeFrontendEvent(['open_registration' => true]);

    $response = $this->post('/events/' . $event->slug . '/register', [
        'name'  => 'Jamie Guest',
        'email' => 'jamie@example.com',
    ]);

    $attendee = $event->attendees()->where('guest_email', 'jamie@example.com')->first();

    expect($attendee)->not->toBeNull();
    $response->assertRedirect($attendee->personalUrl());

    Notification::assertSentOnDemand(EventInvitationNotification::class);
});

it('resends the personal link instead of exposing it when the email is already registered', function() {
    $event = makeFrontendEvent(['open_registration' => true]);
    $attendee = FinisterreEventAttendee::factory()->create([
        'event_id'    => $event->id,
        'guest_email' => 'jamie@example.com',
    ]);

    $this->post('/events/' . $event->slug . '/register', [
        'name'  => 'Someone Else',
        'email' => 'jamie@example.com',
    ])->assertRedirect($event->publicUrl());

    expect($event->attendees()->count())->toBe(1);
});

it('rejects registration when the event is not open for it', function() {
    $event = makeFrontendEvent(['open_registration' => false]);

    $this->post('/events/' . $event->slug . '/register', [
        'name'  => 'Jamie Guest',
        'email' => 'jamie@example.com',
    ])->assertNotFound();
});

it('lets an attendee pick and save availability through the Livewire picker', function() {
    $event = makeFrontendEvent();
    $attendee = FinisterreEventAttendee::factory()->create(['event_id' => $event->id]);

    Livewire::test(AvailabilityPicker::class, ['attendee' => $attendee])
        ->call('toggle', '2030-01-06 09:30:00')
        ->call('toggle', '2030-01-06 23:00:00') // invalid: ignored
        ->call('save')
        ->assertSet('saved', true);

    expect($attendee->refresh()->availability_submitted_at)->not->toBeNull()
        ->and($attendee->slotPicks()->pluck('starts_at')->map->toDateTimeString()->all())
        ->toBe(['2030-01-06 09:30:00']);
});
