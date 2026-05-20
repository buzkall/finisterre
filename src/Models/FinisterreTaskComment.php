<?php

namespace Buzkall\Finisterre\Models;

use Buzkall\Finisterre\Database\Factories\FinisterreTaskCommentFactory;
use Buzkall\Finisterre\Notifications\TaskCommentNotification;
use Buzkall\Finisterre\Observers\FinisterreTaskCommentObserver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $comment
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $sent_at
 * @property array|null $notify_user_ids
 * @property int $creator_id
 * @property int $task_id
 * @property FinisterreTask $task
 */
#[ObservedBy(FinisterreTaskCommentObserver::class)]
class FinisterreTaskComment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['task_id', 'comment', 'creator_id', 'scheduled_for', 'sent_at', 'notify_user_ids'];
    protected $touches = ['task'];
    protected $casts = [
        'scheduled_for'   => 'datetime',
        'sent_at'         => 'datetime',
        'notify_user_ids' => 'array',
    ];

    public function getTable(): string
    {
        return config('finisterre.comments.table_name', 'finisterre_task_comments');
    }

    protected static function newFactory(): FinisterreTaskCommentFactory
    {
        return FinisterreTaskCommentFactory::new();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(FinisterreTask::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('finisterre.authenticatable'), 'creator_id');
    }

    public function scopeVisibleTo(Builder $query, ?int $userId): Builder
    {
        return $query->where(function(Builder $q) use ($userId) {
            $q->whereNull('scheduled_for')
                ->orWhereNotNull('sent_at')
                ->when($userId, fn(Builder $inner) => $inner->orWhere('creator_id', $userId));
        });
    }

    public function isPending(): bool
    {
        return $this->scheduled_for !== null && $this->sent_at === null;
    }

    /**
     * Send notifications, create taskChanges and stamp sent_at.
     */
    public function deliver(): Collection
    {
        $userIds = $this->notify_user_ids ?: [$this->task->assignee_id];
        $userIds = array_filter($userIds, fn($id) => ! is_null($id));

        $users = config('finisterre.authenticatable')::findMany($userIds);
        $notified = collect();

        foreach ($users as $user) {
            $user->notify(new TaskCommentNotification($this));

            Notification::make()
                ->title(__(
                    'finisterre::finisterre.comment_notification.subject',
                    ['title' => $this->task->title]
                ))
                ->body(new HtmlString($this->comment))
                ->actions([
                    Action::make('view')
                        ->label(__('finisterre::finisterre.comment_notification.cta'))
                        ->button()
                        ->url(route('filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.edit', $this->task)),
                ])
                ->sendToDatabase($user);

            if ($user->getKey() !== $this->creator_id) {
                $this->task->taskChanges()->firstOrCreate(['user_id' => $user->getKey()]);
            }

            $notified->push($user);
        }

        $this->forceFill(['sent_at' => now()])->save();

        return $notified;
    }
}
