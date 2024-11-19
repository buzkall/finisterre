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

    public function __construct(public FinisterreTask $task) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(
                '[:priority] Task :title',
                ['priority' => $this->task->priority->getLabel(), 'title' => $this->task->title]
            ))
            ->greeting(__('Changes in task :title', ['title' => $this->task->title]))
            ->line(new HtmlString($this->task->description))
            ->when($this->task->tags->isNotEmpty(), function(MailMessage $mail) {
                $mail->line(__('Tags') . ': ' . new HtmlString($this->task->tags->map(fn($tag) => $tag->name)->implode('<br>')));
            })
            ->when($this->task->comments->isNotEmpty(), function(MailMessage $mail) {
                $mail->line(__('Comments') . ':');
                $mail->line(new HtmlString($this->task->comments->map(fn($comment) => $comment->comment)->implode('<br>')));
            })
            //->action(__('View task'), url(config('finisterre.slug') . '/' . $this->task->id))
            ->action(__('View task'), url(config('finisterre.slug')))
            ->salutation(' ');
    }
}
