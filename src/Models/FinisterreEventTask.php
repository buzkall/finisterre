<?php

namespace Arzcode\Finisterre\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lightweight action item jotted down while the event runs. After the event,
 * the whole list can be turned into a regular Finisterre task whose subtasks
 * are these items.
 *
 * @property int $event_id
 * @property string $title
 * @property bool $completed
 * @property ?int $creator_id
 */
class FinisterreEventTask extends Model
{
    public $fillable = ['event_id', 'title', 'completed', 'creator_id', 'order_column'];
    protected $casts = [
        'completed'    => 'boolean',
        'order_column' => 'integer',
    ];

    public function getTable()
    {
        return 'finisterre_event_tasks';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FinisterreEvent::class, 'event_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'creator_id');
    }
}
