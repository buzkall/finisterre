<?php

namespace Buzkall\Finisterre\Models;

use Buzkall\Finisterre\Database\Factories\FinisterreTaskFactory;
use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FinisterreTask extends Model implements HasMedia, Sortable
{
    use HasFactory, InteractsWithMedia, SortableTrait;

    public $fillable = ['title', 'description', 'status', 'priority', 'due_at', 'completed_at', 'created_by_user_id', 'assigned_to_user_id'];
    protected $casts = [
        'status'       => TaskStatusEnum::class,
        'priority'     => TaskPriorityEnum::class,
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function($task) {
            $task->status = $task->status ?? TaskStatusEnum::Open;
            $task->created_by_user_id = $task->created_by_user_id ?? auth()->id();
        });

        static::updating(function($task) {
            if ($task->isDirty('status') && $task->status == TaskStatusEnum::Done) {
                $task->completed_at = now();
            }
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

    /*
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
    */
}
