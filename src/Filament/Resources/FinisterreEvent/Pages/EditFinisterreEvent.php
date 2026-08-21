<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreEvent\Pages;

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Filament\Resources\FinisterreEventResource;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\EventScheduler;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

/**
 * @property FinisterreEvent $record
 */
class EditFinisterreEvent extends EditRecord
{
    protected static string $resource = FinisterreEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openScheduling')
                ->label(__('finisterre::finisterre.events.open_scheduling'))
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading(__('finisterre::finisterre.events.open_scheduling_heading'))
                ->modalDescription(__('finisterre::finisterre.events.open_scheduling_description'))
                ->visible(fn() => $this->record->status === EventStatusEnum::Draft)
                ->action(function(): void {
                    if ($this->record->windows()->doesntExist() || $this->record->attendees()->doesntExist()) {
                        Notification::make()
                            ->title(__('finisterre::finisterre.events.open_scheduling_missing'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->update(['status' => EventStatusEnum::Scheduling]);

                    Notification::make()
                        ->title(__('finisterre::finisterre.events.open_scheduling_done'))
                        ->success()
                        ->send();
                }),

            Action::make('confirmTime')
                ->label(__('finisterre::finisterre.events.confirm_time'))
                ->icon('heroicon-o-check-circle')
                ->visible(fn() => $this->record->status->acceptsAvailability())
                ->schema([
                    Select::make('slot')
                        ->label(__('finisterre::finisterre.events.confirm_time_slot'))
                        ->options(fn() => $this->slotOptions())
                        ->required(),
                ])
                ->action(function(array $data): void {
                    EventScheduler::for($this->record)->schedule(Carbon::parse($data['slot']));

                    Notification::make()
                        ->title(__('finisterre::finisterre.events.confirm_time_done'))
                        ->success()
                        ->send();
                }),

            Action::make('createFollowUpTask')
                ->label(__('finisterre::finisterre.events.create_follow_up_task'))
                ->icon('heroicon-o-clipboard-document-list')
                ->requiresConfirmation()
                ->modalHeading(__('finisterre::finisterre.events.create_follow_up_task_heading'))
                ->visible(fn() => $this->record->isPast() && $this->record->eventTasks()->exists())
                ->action(function(): void {
                    $task = FinisterreTask::create([
                        'title' => __('finisterre::finisterre.events.follow_up_task_title', [
                            'title' => $this->record->title,
                        ]),
                        'description' => $this->record->private_agenda,
                        'subtasks'    => $this->record->eventTasks
                            ->map(fn($eventTask) => [
                                'title'     => $eventTask->title,
                                'completed' => $eventTask->completed,
                            ])->values()->all(),
                        'priority'   => TaskPriorityEnum::Medium,
                        'creator_id' => auth()->id(),
                    ]);

                    $this->record->update(['status' => EventStatusEnum::Completed]);

                    $this->redirect(route(
                        'filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.edit',
                        $task
                    ));
                }),

            Action::make('cancelEvent')
                ->label(__('finisterre::finisterre.events.cancel_event'))
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('finisterre::finisterre.events.cancel_event_heading'))
                ->visible(fn() => ! in_array($this->record->status, [EventStatusEnum::Completed, EventStatusEnum::Cancelled]))
                ->action(fn() => $this->record->update(['status' => EventStatusEnum::Cancelled])),

            DeleteAction::make(),
        ];
    }

    /**
     * Candidate slots with their acceptance counts, so the creator can confirm
     * one (used both to resolve ties and to pick manually when there is no
     * slot everyone accepts).
     *
     * @return array<string, string>
     */
    protected function slotOptions(): array
    {
        $scheduler = EventScheduler::for($this->record);
        $total = $this->record->attendees()->count();

        $counts = $this->record->slotPicks()->get()
            ->groupBy(fn($pick) => $pick->starts_at->getTimestamp())
            ->map(fn($picks) => $picks->pluck('attendee_id')->unique()->count());

        return $scheduler->candidateSlots()
            ->mapWithKeys(fn(Carbon $slot) => [
                $slot->toDateTimeString() => $slot->isoFormat('LLLL') . ' (' . ($counts[$slot->getTimestamp()] ?? 0) . '/' . $total . ')',
            ])->all();
    }
}
