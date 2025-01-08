<?php

namespace Buzkall\Finisterre\Notifications;

use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class TaskNotification extends Notification
{
    use Queueable;

    public function __construct(public FinisterreTask $task, public array $taskChanges = []) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(
                'finisterre::finisterre.notification.subject',
                ['priority' => $this->task->priority->getLabel(), 'title' => $this->task->title]
            ))
            ->greeting(
                empty($this->taskChanges) ?
                    __('finisterre::finisterre.notification.greeting_new', ['title' => $this->task->title]) :
                    __('finisterre::finisterre.notification.greeting_changes', ['title' => $this->task->title])
            )
            ->line(new HtmlString('<style>img {height: auto !important}</style>'))
            ->when(
                empty($this->taskChanges),
                fn(MailMessage $mail) => $mail->line(new HtmlString($this->task->description)),
                function(MailMessage $mail) {
                    $mail->line(__('finisterre::finisterre.notification.changes'));
                    $mail->line(new HtmlString('<ul>' . collect($this->taskChanges)
                        ->reject(fn($change, $key) => $key == 'updated_at')
                        ->map(fn($value, $key) => '<li>' . __($key) . ': ' . $value . '</li>')
                        ->implode('') . '</ul>'));
                },
            )
            ->when($this->task->tags->isNotEmpty(), function(MailMessage $mail) {
                $mail->line(new HtmlString($this->task->tags->map(fn($tag) => '#' . $tag->name)->implode(', ')));
            })
            ->when($this->task->comments->isNotEmpty(), function(MailMessage $mail) {
                $mail->line(__('finisterre::finisterre.comments.title') . ':');
                $mail->line(new HtmlString($this->task->comments->sortByDesc('created_at')
                    ->map(fn($comment) => $comment->comment . ' ' . $comment->created_at->format('d-m-y H:i:s'))
                    ->implode('<br><hr/>')));
            })
            // ->action(__('finisterre::finisterre.notification.cta'), url(config('finisterre.slug') . '/' . $this->task->id))
            ->action(__('finisterre::finisterre.notification.cta'), url(config('finisterre.slug')))
            ->salutation(' ');
    }
}
