<?php

namespace Buzkall\Finisterre\Models;

use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Notifications\TaskNotification;
use Filament\Notifications\Notification;

class FinisterreTaskReport extends FinisterreTask
{
    protected static function booted(): void
    {
        static::addGlobalScope('reporter', function($query) {
            $query->where('creator_id', auth()->id());
        });

        static::creating(function($task) {
            $task->status = $task->status ?? TaskStatusEnum::Open;
            $task->order_column = 0;
            $task->creator_id = $task->creator_id ?? auth()->id();
            $task->assignee_id = config('finisterre.fallback_notifiable_id');

            defer(function() use ($task) {
                $task->assignee->notify(new TaskNotification($task));

                Notification::make()
                    ->title(__(
                        'finisterre::finisterre.notification.subject',
                        ['priority' => $task->priority->getLabel(), 'title' => $task->title]
                    ))
                    ->body(__('finisterre::finisterre.notification.greeting_new', ['title' => $task->title]))
                    ->sendToDatabase($task->assignee);
            });
        });
    }
}
