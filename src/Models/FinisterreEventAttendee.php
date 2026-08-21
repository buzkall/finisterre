<?php

namespace Arzcode\Finisterre\Models;

use Arzcode\Finisterre\Database\Factories\FinisterreEventAttendeeFactory;
use Arzcode\Finisterre\Notifications\EventInvitationNotification;
use Arzcode\Finisterre\Observers\FinisterreEventAttendeeObserver;
use Arzcode\Finisterre\Support\EventScheduler;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * An event participant: either a panel user (user_id) or an external guest
 * (guest_name/guest_email). Each attendee gets a personal token link for the
 * frontend availability page.
 *
 * @property int $event_id
 * @property ?int $user_id
 * @property ?string $guest_name
 * @property ?string $guest_email
 * @property string $token
 * @property ?Carbon $invited_at
 * @property ?Carbon $availability_submitted_at
 * @property FinisterreEvent $event
 */
#[ObservedBy(FinisterreEventAttendeeObserver::class)]
class FinisterreEventAttendee extends Model
{
    use HasFactory;

    public $fillable = ['event_id', 'user_id', 'guest_name', 'guest_email', 'token',
        'invited_at', 'availability_submitted_at'];
    protected $casts = [
        'invited_at'                => 'datetime',
        'availability_submitted_at' => 'datetime',
    ];

    public function getTable()
    {
        return 'finisterre_event_attendees';
    }

    protected static function newFactory(): FinisterreEventAttendeeFactory
    {
        return FinisterreEventAttendeeFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FinisterreEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'user_id');
    }

    /** @return HasMany<FinisterreEventSlotPick, $this> */
    public function slotPicks(): HasMany
    {
        return $this->hasMany(FinisterreEventSlotPick::class, 'attendee_id');
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function displayName(): string
    {
        if ($this->isGuest()) {
            return $this->guest_name ?? $this->guest_email ?? 'N/A';
        }

        return $this->user?->getUserDisplayName() ?? 'N/A'; // @phpstan-ignore-line method.notFound
    }

    public function email(): ?string
    {
        if ($this->isGuest()) {
            return $this->guest_email;
        }

        return $this->user?->email; // @phpstan-ignore-line property.notFound
    }

    /** Personal frontend link where this attendee picks their availability. */
    public function personalUrl(): string
    {
        return $this->event->publicUrl() . '/a/' . $this->token;
    }

    /**
     * Notify the attendee: panel users through their own notifiable model,
     * guests through an on-demand mail route.
     */
    public function sendNotification(Notification $notification): void
    {
        if (! $this->isGuest()) {
            $this->user?->notify($notification); // @phpstan-ignore-line method.notFound
        } elseif ($this->guest_email) {
            NotificationFacade::route('mail', $this->guest_email)->notify($notification);
        }
    }

    /** Send the personal invitation (with the availability link) and stamp it. */
    public function sendInvitation(): void
    {
        $this->sendNotification(new EventInvitationNotification($this->event, $this));
        $this->forceFill(['invited_at' => now()])->save();
    }

    /**
     * Store the attendee's availability (a list of slot start datetimes), mark
     * it submitted, and re-evaluate the event schedule.
     *
     * @param  iterable<Carbon|string>  $slotStarts
     */
    public function submitAvailability(iterable $slotStarts): void
    {
        $valid = EventScheduler::for($this->event)->candidateSlots()
            ->keyBy(fn(Carbon $slot) => $slot->getTimestamp());

        $starts = collect($slotStarts)
            ->map(fn($start) => $start instanceof Carbon ? $start : Carbon::parse($start))
            ->filter(fn(Carbon $start) => $valid->has($start->getTimestamp()))
            ->unique(fn(Carbon $start) => $start->getTimestamp());

        $this->slotPicks()->delete();

        foreach ($starts as $start) {
            $this->slotPicks()->create([
                'event_id'  => $this->event_id,
                'starts_at' => $start,
            ]);
        }

        $this->update(['availability_submitted_at' => now()]);

        EventScheduler::for($this->event->refresh())->evaluate();
    }
}
