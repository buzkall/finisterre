<?php

namespace Arzcode\Finisterre\Models;

use Arzcode\Finisterre\Database\Factories\FinisterreSubtaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $title
 * @property bool $completed
 * @property ?int $order_column
 * @property int $task_id
 * @property FinisterreTask $task
 */
class FinisterreSubtask extends Model
{
    use HasFactory;

    protected $fillable = ['task_id', 'title', 'completed', 'order_column'];

    // Keep the parent's updated_at honest so the kanban card's "updated X ago"
    // reflects subtask edits. FinisterreTaskObserver::saved() ignores changes
    // limited to updated_at, so this never triggers an assignee notification.
    protected $touches = ['task'];
    protected $casts = [
        'completed'    => 'boolean',
        'order_column' => 'integer',
    ];

    public function getTable(): string
    {
        return config('finisterre.subtasks.table_name', 'finisterre_subtasks');
    }

    protected static function newFactory(): FinisterreSubtaskFactory
    {
        return FinisterreSubtaskFactory::new();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(FinisterreTask::class, 'task_id');
    }
}
