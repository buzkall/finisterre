<?php

namespace Buzkall\Finisterre\Notifications;

use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class TaskCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FinisterreTask $task) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(
                'finisterre::finisterre.comment_notification.subject',
                ['title' => $this->task->title]
            ))
            ->greeting(__('finisterre::finisterre.comment_notification.greeting', ['title' => $this->task->title]))
            ->line(new HtmlString('<style>img {height: auto !important}</style>'))
            ->when($this->task->comments->isNotEmpty(), function(MailMessage $mail) {
                $latestComment = $this->task->comments->last();
                $mail->line(new HtmlString($latestComment->comment));
            })
            ->when($this->task->tags->isNotEmpty(), function(MailMessage $mail) {
                $mail->line(new HtmlString($this->task->tags->map(fn($tag) => '#' . $tag->name)->implode(', ')));
            })
            ->action(
                __('finisterre::finisterre.notification.cta'),
                // note that can't use config slug, because the resource has finisterre-tasks as slug and will override the default one
                url(config('finisterre.panel_slug') . '/finisterre-tasks/' . $this->task->id . '/edit')
            )
            ->salutation(' ');
    }
}
