<?php

namespace Arzcode\Finisterre\Models;

use Arzcode\Finisterre\Database\Factories\FinisterreEventFactory;
use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Observers\FinisterreEventObserver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $title
 * @property string $slug
 * @property ?string $description
 * @property ?string $public_agenda
 * @property ?string $private_agenda
 * @property EventStatusEnum $status
 * @property int $duration_minutes
 * @property bool $requires_confirmation
 * @property bool $open_registration
 * @property ?Carbon $scheduled_start_at
 * @property ?Carbon $scheduled_end_at
 * @property ?string $video_call_url
 * @property ?string $whereby_meeting_id
 * @property ?array $reminder_offsets
 * @property ?array $reminders_sent
 * @property ?int $creator_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<int, FinisterreEventWindow> $windows
 * @property Collection<int, FinisterreEventAttendee> $attendees
 * @property Collection<int, FinisterreEventTask> $eventTasks
 */
#[ObservedBy(FinisterreEventObserver::class)]
class FinisterreEvent extends Model
{
    use HasFactory;

    public $fillable = ['title', 'slug', 'description', 'public_agenda', 'private_agenda', 'status',
        'duration_minutes', 'requires_confirmation', 'open_registration', 'scheduled_start_at',
        'scheduled_end_at', 'video_call_url', 'whereby_meeting_id', 'reminder_offsets', 'reminders_sent',
        'creator_id'];
    protected $casts = [
        'status'                => EventStatusEnum::class,
        'duration_minutes'      => 'integer',
        'requires_confirmation' => 'boolean',
        'open_registration'     => 'boolean',
        'scheduled_start_at'    => 'datetime',
        'scheduled_end_at'      => 'datetime',
        'reminder_offsets'      => 'array',
        'reminders_sent'        => 'array',
    ];

    public function getTable()
    {
        return 'finisterre_events';
    }

    protected static function newFactory(): FinisterreEventFactory
    {
        return FinisterreEventFactory::new();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'creator_id');
    }

    /** @return HasMany<FinisterreEventWindow, $this> */
    public function windows(): HasMany
    {
        return $this->hasMany(FinisterreEventWindow::class, 'event_id')->orderBy('starts_at');
    }

    /** @return HasMany<FinisterreEventAttendee, $this> */
    public function attendees(): HasMany
    {
        return $this->hasMany(FinisterreEventAttendee::class, 'event_id');
    }

    /** @return HasMany<FinisterreEventSlotPick, $this> */
    public function slotPicks(): HasMany
    {
        return $this->hasMany(FinisterreEventSlotPick::class, 'event_id');
    }

    /** @return HasMany<FinisterreEventTask, $this> */
    public function eventTasks(): HasMany
    {
        return $this->hasMany(FinisterreEventTask::class, 'event_id')->orderBy('order_column');
    }

    public function creatorName(): string
    {
        $creator = $this->creator;
        if (! $creator) {
            return 'N/A';
        }

        /** @var Authenticatable $creator */
        return $creator->getUserDisplayName();
    }

    /** The public event page, outside Filament. */
    public function publicUrl(): string
    {
        return url(config('finisterre.events.route_prefix', 'events') . '/' . $this->slug);
    }

    /** The Filament edit page for the event. */
    public function panelUrl(): string
    {
        return route(
            'filament.' . config('finisterre.panel_slug') . '.resources.finisterre-events.edit',
            $this
        );
    }

    public function attendeeForUser(Authenticatable|int|null $user): ?FinisterreEventAttendee
    {
        $id = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

        if (! $id) {
            return null;
        }

        return $this->attendees->firstWhere('user_id', $id);
    }

    /** Whether every attendee has submitted their availability (and there is at least one). */
    public function allAvailabilitySubmitted(): bool
    {
        return $this->attendees()->exists()
            && $this->attendees()->whereNull('availability_submitted_at')->doesntExist();
    }

    /** Whereby Embedded rooms can be shown in an iframe; other URLs only get a join link. */
    public function videoCallIsEmbeddable(): bool
    {
        return filled($this->video_call_url) && filled($this->whereby_meeting_id);
    }

    public function isPast(): bool
    {
        return $this->scheduled_end_at !== null && $this->scheduled_end_at->isPast();
    }

    /**
     * Reminder offsets (minutes before the scheduled start) for this event,
     * falling back to the configured default.
     *
     * @return list<int>
     */
    public function reminderOffsets(): array
    {
        $offsets = $this->reminder_offsets ?? config('finisterre.events.default_reminder_offsets', []);

        return collect($offsets)->map(fn($offset) => (int)$offset)->filter(fn($offset) => $offset > 0)
            ->unique()->sortDesc()->values()->all();
    }
}
