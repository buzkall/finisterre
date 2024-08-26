<?php

namespace Buzkall\Finisterre\Models;

//use App\Models\User;
use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStateEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    public $fillable = ['title', 'description', 'state', 'priority', 'due_at', 'completed_at', 'created_by_user_id', 'assigned_to_user_id'];
    protected $casts = [
        'state'        => TaskStateEnum::class,
        'priority'     => TaskPriorityEnum::class,
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function($task) {
            $task->state = $task->state ?? TaskStateEnum::Open;
            $task->created_by_user_id = $task->created_by_user_id ?? auth()->id();
        });

        static::updating(function($task) {
            if ($task->isDirty('state') && $task->state == TaskStateEnum::Done) {
                $task->completed_at = now();
            }
        });
    }

    public function getTable()
    {
        return config('finisterre.table_name');
    }

    /*
        public function creator(): BelongsTo
        {
            return $this->belongsTo(User::class, 'created_by_user_id');
        }

        public function assignee(): BelongsTo
        {
            return $this->belongsTo(User::class, 'assigned_to_user_id');
        }*/
}
