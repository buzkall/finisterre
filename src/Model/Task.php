<?php

namespace App\Models;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskStateEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Task extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $connection = 'mysql_master';
    protected $fillable = ['title', 'description', 'state', 'priority', 'due_at', 'completed_at',
        'created_by_user_id', 'assigned_to_user_id'];

    protected static function booted(): void
    {
        static::creating(function($task) {
            $task->state ??= TaskStateEnum::Open;
            $task->created_by_user_id ??= auth()->id();
        });

        static::updating(function($task) {
            if ($task->isDirty('state') && $task->state == TaskStateEnum::Done) {
                $task->completed_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'state'        => TaskStateEnum::class,
            'priority'     => TaskPriorityEnum::class,
            'due_at'       => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
