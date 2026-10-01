<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Notifications\Concerns\EmbedsPrivateImages;
use Arzcode\Finisterre\Notifications\Concerns\UsesFinisterreMailLayout;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ScheduledCommentSentNotification extends Notification implements ShouldQueue
{
    use EmbedsPrivateImages, Queueable, UsesFinisterreMailLayout;

    public function __construct(public FinisterreTaskComment $comment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->comment->task;

        $mail = $this->newMailMessage($task)
            ->subject(__(
                'finisterre::finisterre.scheduled_comment_sent.subject',
                ['title' => $task->title]
            ))
            ->greeting(__('finisterre::finisterre.scheduled_comment_sent.greeting'))
            ->line(new HtmlString('<style>img {height: auto !important}</style>'))
            ->line(new HtmlString($this->embedImages($this->comment->comment)))
            ->action(
                __('finisterre::finisterre.scheduled_comment_sent.cta'),
                route('filament.' . Typed::string(config('finisterre.panel_slug')) . '.resources.finisterre-tasks.view', $task)
            )
            ->salutation(' ');

        return $this->withInlineImages($mail);
    }
}
