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

    /**
     * Create a new notification instance.
     */
    public function __construct(public FinisterreTask $task)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        ray($this->task);

        return (new MailMessage)
            ->subject(__('Task :title', ['title' => $this->task->title]))
            ->line(new HtmlString($this->task->description))
            ->when($this->task->comments->isNotEmpty(), function(MailMessage $mail) {
                $mail->line('Comments:');
                $mail->line(new HtmlString($this->task->comments->map(fn($comment) => $comment->comment)->implode('<br>')));
            })
            //->action(__('View task'), url(config('finisterre.slug') . '/' . $this->task->id))
            ->action(__('View task'), url(config('finisterre.slug')))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
