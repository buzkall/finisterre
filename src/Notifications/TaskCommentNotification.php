<?php

namespace Buzkall\Finisterre\Notifications;

use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class TaskCommentNotification extends Notification
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

            ->when($this->task->comments->isNotEmpty(), function(MailMessage $mail) {
                $latestComment = $this->task->comments->last();
                $mail->line(new HtmlString($latestComment->comment));
            })
            ->when($this->task->tags->isNotEmpty(), function(MailMessage $mail) {
                $mail->line(new HtmlString($this->task->tags->map(fn($tag) => '#' . $tag->name)->implode(', ')));
            })
            //->action(__('finisterre::finisterre.notification.cta'), url(config('finisterre.slug') . '/' . $this->task->id))
            ->action(__('finisterre::finisterre.notification.cta'), url(config('finisterre.slug')))
            ->salutation(' ');
    }
}
