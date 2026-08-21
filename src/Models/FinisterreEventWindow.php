<?php

namespace Arzcode\Finisterre\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A day/time range the event creator suggests; candidate meeting slots are
 * generated inside these windows.
 *
 * @property int $event_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
class FinisterreEventWindow extends Model
{
    public $fillable = ['event_id', 'starts_at', 'ends_at'];
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function getTable()
    {
        return 'finisterre_event_windows';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FinisterreEvent::class, 'event_id');
    }
}
