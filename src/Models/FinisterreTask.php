<?php

namespace Buzkall\Finisterre\Models;

use Buzkall\Finisterre\Database\Factories\FinisterreTaskFactory;
use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Notifications\TaskNotification;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

/**
 * @property string $title
 * @property string $description
 * @property \Illuminate\Support\Collection $tags
 * @property \Illuminate\Support\Collection $comments
 * @property TaskStatusEnum $status
 * @property bool $archived
 * @property TaskPriorityEnum $priority
 * @property array $subtasks
 * @property \Illuminate\Support\Carbon $due_at
 * @property \Illuminate\Support\Carbon $completed_at
 * @property int $creator_id
 * @property int $assignee_id
 */
class FinisterreTask extends Model implements HasMedia, Sortable
{
    use HasFactory, HasTags, InteractsWithMedia, SortableTrait;

    public $fillable = ['title', 'description', 'status', 'archived', 'priority', 'subtasks', 'due_at', 'completed_at',
        'creator_id', 'assignee_id'];
    protected $casts = [
        'status'       => TaskStatusEnum::class,
        'archived'     => 'boolean',
        'priority'     => TaskPriorityEnum::class,
        'subtasks'     => 'array',
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];
    protected $with = ['tags'];

    protected static function booted(): void
    {
        // add global scope only for reporter users
        if (self::userCanOnlyReport()) {
            static::addGlobalScope('reporter', function($query) {
                $query->where('creator_id', auth()->id());
            });
        }

        static::creating(function($task) {
            $task->status = $task->status ?? TaskStatusEnum::Open;
            $task->order_column = 0;
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
            // If the only dirty field is the updated_at timestamp, means that it has been
            // touched by a comment, that has its own notification logic, so skip notification here
            if (empty($task->getDirty()) ||
                (count($task->getDirty()) === 1 && array_key_exists('updated_at', $task->getDirty()))) {
                return;
            }

            defer(function() use ($task) {
                if (is_null($task->assignee_id)) {
                    $task->assignee_id = config('finisterre.fallback_notifiable_id');
                }

                // don't notify myself
                if ($task->assignee && $task->assignee->id !== auth()->id()) {
                    $taskChanges = $task->getChanges();
                    $task->assignee->notify(new TaskNotification($task, $taskChanges));

                    Notification::make()
                        ->title(__(
                            'finisterre::finisterre.notification.subject',
                            ['priority' => $task->priority->getLabel(), 'title' => $task->title]
                        ))
                        ->body(empty($taskChanges) ?
                            __('finisterre::finisterre.notification.greeting_new', ['title' => $task->title]) :
                            __('finisterre::finisterre.notification.greeting_changes', ['title' => $task->title]))
                        ->actions([
                            Action::make('view')
                                ->label(__('finisterre::finisterre.comment_notification.cta'))
                                ->button()
                                ->url(route('filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.edit', $task)),
                        ])
                        ->sendToDatabase($task->assignee);
                }
            });
        });
    }

    public function getTable()
    {
        return config('finisterre.table_name');
    }

    public static function userCanOnlyReport(): bool
    {
        if (! auth()->user() || empty(config('finisterre.can_only_report_filter_column'))) {
            return false;
        }

        return auth()->user()->{config('finisterre.can_only_report_filter_column')} === config('finisterre.can_only_report_filter_value');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('archived', false);
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
