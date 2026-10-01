<?php

namespace Arzcode\Finisterre\Notifications;

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\Concerns\EmbedsPrivateImages;
use Arzcode\Finisterre\Notifications\Concerns\RendersTaskHistory;
use Arzcode\Finisterre\Notifications\Concerns\UsesFinisterreMailLayout;
use Arzcode\Finisterre\Support\Typed;
use Arzcode\Finisterre\Support\UserModel;
use DateTimeInterface;
use Exception;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;

class TaskNotification extends Notification implements ShouldQueue
{
    use EmbedsPrivateImages, Queueable, RendersTaskHistory, UsesFinisterreMailLayout;

    protected bool $wasRecentlyCreated = false;

    /** @param  array<string, mixed>  $taskChanges */
    public function __construct(public FinisterreTask $task, public array $taskChanges = [])
    {
        $this->wasRecentlyCreated = $task->wasRecentlyCreated;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', SMSChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // a new task's email already shows the description above the history
        $history = $this->taskHistoryHtml($this->task, withDescription: $this->taskChanges !== []);

        $relatedRecord = $this->task->subjectReportLink();

        $mail = $this->newMailMessage($this->task)
            ->subject(__(
                'finisterre::finisterre.notification.subject',
                ['priority' => $this->task->priority->getLabel(), 'title' => $this->task->title]
            ))
            ->greeting(
                $this->taskChanges === [] ?
                    __('finisterre::finisterre.notification.greeting_new', ['title' => $this->task->title]) :
                    __('finisterre::finisterre.notification.greeting_changes', ['title' => $this->task->title])
            )
            ->line(new HtmlString('<style>img {height: auto !important}</style>'))
            ->line(__('finisterre::finisterre.created_by') . ': ' . $this->task->creatorName())
            ->when($relatedRecord, fn(MailMessage $mail) => $mail->line($relatedRecord))
            ->when(
                $this->taskChanges === [],
                fn(MailMessage $mail) => $mail->when(
                    filled($this->task->description),
                    fn(MailMessage $mail) => $mail->line(new HtmlString($this->embedImages($this->task->description)))
                ),
                function(MailMessage $mail) {
                    $mail->line(__('finisterre::finisterre.notification.changes'));
                    $mail->line(new HtmlString('<ul>' . $this->changeLines() . '</ul>'));
                },
            )
            ->when($this->task->tags->isNotEmpty(), function(MailMessage $mail) {
                $mail->line(new HtmlString($this->task->tags->map(fn($tag) => '<span style="display:inline-block;background-color:#e5e7eb;color:#374151;padding:2px 10px;margin:2px 4px 2px 0;border-radius:9999px;font-size:13px;line-height:1.6;">#' . e($tag->name) . '</span>')->implode('')));
            })
            ->when($history, fn(MailMessage $mail) => $mail->line($history))
            ->action(
                __('finisterre::finisterre.notification.cta'),
                route('filament.' . Typed::string(config('finisterre.panel_slug')) . '.resources.finisterre-tasks.view', $this->task)
            )
            ->salutation(' ');

        return $this->withInlineImages($mail);
    }

    /**
     * The changed fields as list items, with translated labels and readable values.
     * Changes hold raw column values, so they are read back through a model to get
     * the enums and dates its casts produce.
     */
    protected function changeLines(): string
    {
        $changes = collect($this->taskChanges)
            ->except(['updated_at', 'order_column', 'cover_media_id', 'editor_files', 'subject_type', 'subject_id']);

        $casted = (new FinisterreTask)->setRawAttributes($changes->all());

        return $changes
            ->map(fn($value, string $key) => '<li>' . e($this->changeLabel($key)) .
                // the description is rich text: its label says enough
                ($key === 'description' ? '' : ': ' . e($this->changeValue($key, $casted))) . '</li>')
            ->implode('');
    }

    protected function changeLabel(string $key): string
    {
        return trans()->has('finisterre::finisterre.' . $key) ? __('finisterre::finisterre.' . $key) : __($key);
    }

    protected function changeValue(string $key, FinisterreTask $casted): string
    {
        $value = $casted->getAttribute($key);

        return match (true) {
            in_array($key, ['assignee_id', 'creator_id'], true) => $this->userName($value),
            $value === null                                     => '-',
            $value instanceof HasLabel                          => $this->labelText($value),
            $value instanceof DateTimeInterface                 => $value->format('d-m-y H:i'),
            is_bool($value)                                     => __('finisterre::finisterre.' . ($value ? 'yes' : 'no')),
            default                                             => Typed::string($value),
        };
    }

    protected function labelText(HasLabel $value): string
    {
        $label = $value->getLabel();

        return $label instanceof Htmlable ? $label->toHtml() : (string)$label;
    }

    protected function userName(mixed $id): string
    {
        if (blank($id)) {
            return __('finisterre::finisterre.unassigned');
        }

        $user = UserModel::class()::query()->whereKey($id)->first();

        return $user ? UserModel::displayName($user) : 'N/A';
    }

    public function toSms(object $notifiable): void
    {
        if (config('finisterre.sms_notification.enabled') === false) {
            return;
        }

        if (! in_array($this->task->priority, Typed::array(config('finisterre.sms_notification.notify_priorities')))) {
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
                Http::timeout(10)->get(Typed::string(config('finisterre.sms_notification.url')), [
                    'auth_key' => config('finisterre.sms_notification.auth_key'),
                    'id'       => $this->task->id . '_' . now()->timestamp,
                    'from'     => config('finisterre.sms_notification.sender'),
                    'to'       => config('finisterre.sms_notification.notify_to'),
                    'text'     => __(
                        'finisterre::finisterre.notification.sms',
                        [
                            'priority' => $this->task->priority->getLabel(),
                            'title'    => $this->task->title,
                            'creator'  => $this->task->creatorName(),
                        ]
                    )]);

                return;
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
