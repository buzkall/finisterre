<?php

namespace Arzcode\Finisterre\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A concrete time frame (starting at starts_at, lasting the event's estimated
 * duration) an attendee marked themselves available for.
 *
 * @property int $event_id
 * @property int $attendee_id
 * @property Carbon $starts_at
 */
class FinisterreEventSlotPick extends Model
{
    public $fillable = ['event_id', 'attendee_id', 'starts_at'];
    protected $casts = [
        'starts_at' => 'datetime',
    ];

    public function getTable()
    {
        return 'finisterre_event_slot_picks';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FinisterreEvent::class, 'event_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(FinisterreEventAttendee::class, 'attendee_id');
    }
}
