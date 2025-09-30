<?php

namespace Buzkall\Finisterre\Notifications;

use Buzkall\Finisterre\Models\FinisterreTask;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;

class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected bool $wasRecentlyCreated = false;

    public function __construct(public FinisterreTask $task, public array $taskChanges = [])
    {
        $this->wasRecentlyCreated = $task->wasRecentlyCreated;
    }

    public function via(object $notifiable): array
    {
        return ['mail', SMSChannel::class];
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
            ->line(__('finisterre::finisterre.created_by') . ': ' . $this->task->creatorName())
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
            ->action(
                __('finisterre::finisterre.notification.cta'),
                // note that can't use config slug, because the resource has finisterre-tasks as slug and will override the default one
                url(config('finisterre.panel_slug') . '/finisterre-tasks/' . $this->task->id . '/edit')
            )
            ->salutation(' ');
    }

    public function toSms(object $notifiable): void
    {
        if (config('finisterre.sms_notification.enabled') === false) {
            return;
        }

        if (! in_array($this->task->priority, config('finisterre.sms_notification.notify_priorities'))) {
            return;
        }

        // only notify on creation
        // using a queue, can't use $this->task->wasRecentlyCreated because will always be false
        if (! $this->wasRecentlyCreated) {
            return;
        }

        // Make a GET call to:
        // https://api.smsarena.es/http/sms.php?auth_key=XXXX&id=11964&from=XXXX&to=XXXX&text=XXXX

        $maxRetries = 3;
        $retryDelay = 1; // seconds
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Http::timeout(10)->get(config('finisterre.sms_notification.url'), [
                    'auth_key' => config('finisterre.sms_notification.auth_key'),
                    'id'       => $this->task->id . '_' . now()->timestamp,
                    'from'     => config('finisterre.sms_notification.sender'),
                    'to'       => config('finisterre.sms_notification.notify_to'),
                    'text'     => __(
                        'finisterre::finisterre.notification.subject',
                        ['priority' => $this->task->priority->getLabel(), 'title' => $this->task->title]
                    )]);

            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'Could not resolve host') &&
                    $attempt < $maxRetries) {
                    sleep($retryDelay);

                    continue;
                }

                throw $e;
            }
        }
    }
}
