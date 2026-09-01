<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * One digest covering every checklist change made by someone else during the
 * notification window. The lines are pre-folded by
 * SendSubtaskChangesNotification, which is the only thing that builds this.
 */
class SubtaskChangesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $entries
     */
    public function __construct(public FinisterreTask $task, public array $entries = []) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->theme('finisterre::themes.finisterre')
            ->subject(__('finisterre::finisterre.subtask_changes.subject', ['title' => $this->task->title]))
            ->greeting(__('finisterre::finisterre.subtask_changes.greeting', ['title' => $this->task->title]))
            // Subtask titles are user input and the lines are plain text, so
            // they are escaped here rather than trusted into the markup.
            ->line(new HtmlString('<ul>' . collect($this->entries)
                ->map(fn(string $entry) => '<li>' . e($entry) . '</li>')
                ->implode('') . '</ul>'))
            ->action(
                __('finisterre::finisterre.subtask_changes.cta'),
                route(
                    'filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.edit',
                    $this->task
                )
            )
            ->salutation(' ');
    }
}
