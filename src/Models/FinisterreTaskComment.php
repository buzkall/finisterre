<?php

namespace Arzcode\Finisterre\Models;

use Arzcode\Finisterre\Casts\SafeHtmlCast;
use Arzcode\Finisterre\Database\Factories\FinisterreTaskCommentFactory;
use Arzcode\Finisterre\Notifications\ScheduledCommentSentNotification;
use Arzcode\Finisterre\Notifications\TaskCommentNotification;
use Arzcode\Finisterre\Observers\FinisterreTaskCommentObserver;
use Arzcode\Finisterre\Support\TaskFollowers;
use Arzcode\Finisterre\Support\Typed;
use Arzcode\Finisterre\Support\UserModel;
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
use Throwable;

/**
 * @property int $id
 * @property string $comment
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $sent_at
 * @property list<int>|null $notify_user_ids
 * @property string|null $email_message_id
 * @property ?int $creator_id
 * @property int $task_id
 * @property Carbon|null $created_at
 * @property FinisterreTask $task
 */
#[ObservedBy(FinisterreTaskCommentObserver::class)]
class FinisterreTaskComment extends Model implements HasMedia
{
    /** @use HasFactory<FinisterreTaskCommentFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['task_id', 'comment', 'creator_id', 'scheduled_for', 'sent_at', 'notify_user_ids', 'email_message_id'];

    /** @var list<string> */
    protected $touches = ['task'];

    public function getTable(): string
    {
        return Typed::string(config('finisterre.comments.table_name', 'finisterre_task_comments'));
    }

    protected static function newFactory(): FinisterreTaskCommentFactory
    {
        return FinisterreTaskCommentFactory::new();
    }

    /** @return BelongsTo<FinisterreTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(FinisterreTask::class);
    }

    /** @return BelongsTo<Model, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'creator_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
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
     * Mark the comment as sent, unless it already is. One conditional UPDATE decides, so
     * two workers (or two servers) racing for the same comment cannot both win it: the
     * one that gets true delivers it, the other must not.
     */
    public function claim(): bool
    {
        $sentAt = now();

        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereNull('sent_at')
            ->update(['sent_at' => $sentAt]) === 1;

        if ($claimed) {
            $this->forceFill(['sent_at' => $sentAt])->syncOriginalAttribute('sent_at');
            $this->touchOwners();
        }

        return $claimed;
    }

    /**
     * Hand the comment back unsent, for the next run to deliver: what claim() did,
     * undone.
     */
    protected function release(): void
    {
        static::query()->whereKey($this->getKey())->update(['sent_at' => null]);

        $this->forceFill(['sent_at' => null])->syncOriginalAttribute('sent_at');
    }

    /**
     * Send notifications and create taskChanges, once: the caller that wins claim()
     * delivers, and any other gets null back. Until somebody has been notified a
     * failure hands the claim back, so the comment is delivered on the next run.
     * After that a recipient whose notification fails is reported and skipped,
     * since retrying would notify everybody else a second time.
     *
     * @return Collection<int, Model>|null The people notified, or null when another caller delivers it
     */
    public function deliver(): ?Collection
    {
        if (! $this->claim()) {
            return null;
        }

        $wasScheduled = $this->scheduled_for !== null;

        try {
            // A comment that names nobody goes to the task's followers, never back to
            // its own author. An empty list is somebody's choice to notify no one.
            $users = UserModel::class()::findMany(
                $this->notify_user_ids ?? array_diff(TaskFollowers::ids($this->task), [$this->creator_id])
            );
        } catch (Throwable $throwable) {
            $this->release();

            throw $throwable;
        }

        // One recipient whose notification fails is reported, and the rest still get theirs.
        $notified = $users->filter(fn(Model $user): bool => rescue(function() use ($user): bool {
            $this->notifyUser($user);

            return true;
        }, false))->values();

        if ($wasScheduled && $this->creator) {
            rescue(fn() => UserModel::notify($this->creator, new ScheduledCommentSentNotification($this)));
        }

        return $notified;
    }

    protected function notifyUser(Model $user): void
    {
        UserModel::notify($user, new TaskCommentNotification($this));

        Notification::make()
            ->title(__(
                'finisterre::finisterre.comment_notification.subject',
                ['title' => $this->task->title]
            ))
            ->body(new HtmlString((string)$this->comment))
            ->actions([
                Action::make('view')
                    ->label(__('finisterre::finisterre.comment_notification.cta'))
                    ->button()
                    ->url(route('filament.' . Typed::string(config('finisterre.panel_slug')) . '.resources.finisterre-tasks.view', $this->task)),
            ])
            ->sendToDatabase($user);

        if ($user->getKey() !== $this->creator_id) {
            $this->task->taskChanges()->firstOrCreate(['user_id' => $user->getKey()]);
        }
    }

    protected function casts(): array
    {
        return [
            'scheduled_for'   => 'datetime',
            'sent_at'         => 'datetime',
            'notify_user_ids' => 'array',
            'comment'         => SafeHtmlCast::class,
            'creator_id'      => 'integer',
        ];
    }
}
