<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Notifications\Concerns\EmbedsPrivateImages;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class TaskCommentNotification extends Notification implements ShouldQueue
{
    use EmbedsPrivateImages, Queueable;

    public function __construct(public FinisterreTaskComment $comment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->comment->task;

        $mail = (new MailMessage)
            ->theme('finisterre::themes.finisterre')
            ->subject(__(
                'finisterre::finisterre.comment_notification.subject',
                ['title' => $task->title]
            ))
            ->greeting(__('finisterre::finisterre.comment_notification.greeting', ['title' => $task->title]))
            ->line(new HtmlString('<style>img {height: auto !important}</style>'))
            ->line(new HtmlString($this->embedImages($this->comment->comment)))
            ->when($task->tags->isNotEmpty(), function(MailMessage $mail) use ($task) {
                $mail->line(new HtmlString($task->tags->map(fn($tag) => '<span style="display:inline-block;background-color:#e5e7eb;color:#374151;padding:2px 10px;margin:2px 4px 2px 0;border-radius:9999px;font-size:13px;line-height:1.6;">#' . e($tag->name) . '</span>')->implode('')));
            })
            ->action(
                __('finisterre::finisterre.notification.cta'),
                route('filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.edit', $task)
            )
            ->salutation(' ');

        return $this->withInlineImages($mail);
    }
}
