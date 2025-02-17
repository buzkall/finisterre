<?php

namespace Buzkall\Finisterre\Models;

use Buzkall\Finisterre\Database\Factories\FinisterreTaskFactory;
use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Notifications\TaskNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class FinisterreTask extends Model implements HasMedia, Sortable
{
    use HasFactory, HasTags, InteractsWithMedia, SortableTrait;

    public $fillable = ['title', 'description', 'status', 'priority', 'due_at', 'completed_at',
        'creator_id', 'assignee_id'];
    protected $casts = [
        'status'       => TaskStatusEnum::class,
        'priority'     => TaskPriorityEnum::class,
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];
    protected $with = ['tags'];

    protected static function booted(): void
    {
        static::creating(function($task) {
            $task->status = $task->status ?? TaskStatusEnum::Open;
            $task->creator_id = $task->creator_id ?? auth()->id();
        });

        static::updating(function($task) {
            if ($task->isDirty('status')) {
                if ($task->status === TaskStatusEnum::Done) {
                    $task->completed_at = now();
                } elseif (! is_null($task->completed_at)) {
                    $task->completed_at = null;
                }
            }
        });

        static::saved(function($task) {
            defer(function() use ($task) {
                if (is_null($task->assignee_id)) {
                    $task->assignee_id = config('finisterre.fallback_notifiable_id');
                }
                if ($task->assignee && $task->assignee->id !== auth()->id()) { // don't notify myself
                    $task->assignee->notify(new TaskNotification($task, $task->getChanges()));
                }
            });
        });
    }

    public function getTable()
    {
        return config('finisterre.table_name');
    }

    protected static function newFactory(): FinisterreTaskFactory
    {
        return FinisterreTaskFactory::new();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FinisterreTaskComment::class, 'task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'creator_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'assignee_id');
    }
}
