<?php

namespace Buzkall\Finisterre\Notifications;

use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Buzkall\Finisterre\Notifications\Concerns\EmbedsPrivateImages;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ScheduledCommentSentNotification extends Notification implements ShouldQueue
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
                'finisterre::finisterre.scheduled_comment_sent.subject',
                ['title' => $task->title]
            ))
            ->greeting(__('finisterre::finisterre.scheduled_comment_sent.greeting'))
            ->line(new HtmlString('<style>img {height: auto !important}</style>'))
            ->line(new HtmlString($this->embedImages($this->comment->comment)))
            ->action(
                __('finisterre::finisterre.scheduled_comment_sent.cta'),
                route('filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.edit', $task)
            )
            ->salutation(' ');

        return $this->withInlineImages($mail);
    }
}
