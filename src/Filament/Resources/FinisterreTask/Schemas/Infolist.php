<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas;

use Arzcode\Finisterre\Contracts\FinisterreReportable;
use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Filament\Livewire\FinisterreSubtasksComponent;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ViewFinisterreTask;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Layout of the task page: a strip of badges that double as quick actions,
 * the description and attachments, the subtasks panel and a small footer.
 * The comments component is appended by the page view.
 */
class Infolist
{
    public static function configure(Schema $schema): Schema
    {
        $userIsReporterOnly = FinisterrePlugin::get()->canViewOnlyTheirTasks();
        $canQuickEdit = fn(FinisterreTask $record): bool => auth()->user()?->can('update', $record) ?? false;

        return $schema
            ->columns(1)
            ->components([
                Actions::make(self::quickActions($userIsReporterOnly))
                    ->key('quick_actions')
                    ->visible($canQuickEdit),

                // Same values, no actions, for users who may only look.
                ViewEntry::make('read_only_strip')
                    ->key('read_only_strip')
                    ->hiddenLabel()
                    ->view('finisterre::tasks.read-only-badges')
                    ->viewData(fn(FinisterreTask $record) => ['showDueDate' => ! $userIsReporterOnly])
                    ->hidden($canQuickEdit),

                Section::make()
                    ->compact()
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->html()
                            ->prose()
                            ->placeholder(__('finisterre::finisterre.no_description')),

                        ViewEntry::make('attachments')
                            ->hiddenLabel()
                            ->view('finisterre::tasks.attachments')
                            ->viewData(fn(FinisterreTask $record) => ['media' => $record->getMedia('tasks')])
                            ->visible(fn(FinisterreTask $record) => $record->getMedia('tasks')->isNotEmpty()),
                    ]),

                Section::make(__('finisterre::finisterre.subtasks.label'))
                    ->icon('heroicon-o-check-circle')
                    ->collapsible()
                    // Open when there is something to see, folded away otherwise.
                    ->collapsed(fn(FinisterreTask $record) => $record->subtasks->isEmpty())
                    ->afterHeader(fn(FinisterreTask $record): HtmlString => new HtmlString(
                        view('finisterre::subtasks.counter-badge', [
                            'done'  => $record->subtasks->where('completed', true)->count(),
                            'total' => $record->subtasks->count(),
                        ])->render()
                    ))
                    ->schema([
                        Livewire::make(FinisterreSubtasksComponent::class)->key('finisterre-subtasks'),
                    ])
                    ->hidden($userIsReporterOnly),

                TextEntry::make('subject')
                    ->label(__('finisterre::finisterre.related_record'))
                    ->visible(fn(FinisterreTask $record) => $record->subject instanceof FinisterreReportable)
                    ->state(fn(FinisterreTask $record): ?HtmlString => $record->subjectReportLink()),

                TextEntry::make('dates')
                    ->hiddenLabel()
                    ->hintIcon('heroicon-o-clock')
                    ->hint(fn(FinisterreTask $record) => new HtmlString(
                        __('finisterre::finisterre.created_by') . ': ' .
                        '&nbsp;&nbsp;&nbsp;&nbsp;' .
                        $record->creatorName() .
                        '<br />' .
                        __('finisterre::finisterre.created_at') . ': ' .
                        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .
                        $record->created_at->format('d/m/y H:i:s') .
                        '<br />' .
                        __('finisterre::finisterre.updated_at') . ': ' . $record->updated_at->format('d/m/y H:i:s')
                    ))
                    ->alignEnd(),
            ]);
    }

    /**
     * One badge per value. Status, priority and assignee change with a single
     * click from a dropdown; tags and due date open a one-field modal.
     * Reporters keep what the form allowed them: status, priority and tags.
     *
     * @return array<Action|ActionGroup>
     */
    protected static function quickActions(bool $userIsReporterOnly): array
    {
        // An ActionGroup does not resolve its record through the schema
        // component the way a plain Action does, so bind it explicitly or
        // the trigger's label/color closures receive null.
        $bindRecord = fn(Component $schemaComponent) => $schemaComponent->getRecord();

        $statusActions = TaskStatusEnum::filteredCases()
            ->map(fn(TaskStatusEnum $status): Action => Action::make('status_' . $status->value)
                ->label($status->getLabel())
                ->icon(fn(FinisterreTask $record) => $record->status === $status ? Heroicon::Check : null)
                ->action(fn(FinisterreTask $record, ViewFinisterreTask $livewire) => self::apply(
                    $livewire,
                    fn() => $record->update(['status' => $status])
                )))
            ->all();

        $priorityActions = collect(TaskPriorityEnum::cases())
            ->map(fn(TaskPriorityEnum $priority): Action => Action::make('priority_' . $priority->value)
                ->label($priority->getLabel())
                ->color($priority->getColor())
                ->icon(fn(FinisterreTask $record) => $record->priority === $priority ? Heroicon::Check : null)
                ->action(fn(FinisterreTask $record, ViewFinisterreTask $livewire) => self::apply(
                    $livewire,
                    fn() => $record->update(['priority' => $priority])
                )))
            ->all();

        $assigneeActions = config('finisterre.authenticatable')::query()
            ->assignableUsers()
            ->get()
            ->map(fn($user): Action => Action::make('assignee_' . $user->getKey())
                ->label($user->getUserDisplayName())
                ->icon(fn(FinisterreTask $record) => $record->assignee_id === $user->getKey() ? Heroicon::Check : null)
                ->action(fn(FinisterreTask $record, ViewFinisterreTask $livewire) => self::apply(
                    $livewire,
                    fn() => $record->update(['assignee_id' => $user->getKey()])
                )))
            ->all();

        return [
            ActionGroup::make($statusActions)
                ->record($bindRecord)
                ->badge()
                ->label(fn(FinisterreTask $record) => $record->status->getLabel())
                ->color(fn(FinisterreTask $record) => $record->status->getColor())
                ->icon(Heroicon::ChevronDown)
                ->iconPosition(IconPosition::After)
                ->tooltip(__('finisterre::finisterre.status'))
                ->dropdownPlacement('bottom-start'),

            ActionGroup::make($priorityActions)
                ->record($bindRecord)
                ->badge()
                ->label(fn(FinisterreTask $record) => $record->priority->getLabel())
                ->color(fn(FinisterreTask $record) => $record->priority->getColor())
                ->icon(Heroicon::ChevronDown)
                ->iconPosition(IconPosition::After)
                ->tooltip(__('finisterre::finisterre.priority'))
                ->dropdownPlacement('bottom-start'),

            ActionGroup::make($assigneeActions)
                ->record($bindRecord)
                ->badge()
                ->color('gray')
                ->icon(Heroicon::OutlinedUser)
                ->label(fn(FinisterreTask $record) => $record->assigneeName() ?? __('finisterre::finisterre.unassigned'))
                ->tooltip(__('finisterre::finisterre.assignee_id'))
                ->dropdownPlacement('bottom-start')
                ->dropdownWidth(Width::ExtraSmall)
                // Reporters could never reassign on the form either.
                ->hidden($userIsReporterOnly),

            Action::make('quick_due_at')
                ->badge()
                ->color(fn(FinisterreTask $record) => $record->due_at?->isPast() && $record->status !== TaskStatusEnum::Done ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedCalendar)
                ->label(fn(FinisterreTask $record) => $record->due_at?->format('d/m/y') ?? __('finisterre::finisterre.no_due_date'))
                ->tooltip(__('finisterre::finisterre.due_at'))
                ->modalHeading(__('finisterre::finisterre.due_at'))
                ->modalWidth(Width::Small)
                ->fillForm(fn(FinisterreTask $record) => ['due_at' => $record->due_at])
                ->schema([
                    DatePicker::make('due_at')
                        ->label(__('finisterre::finisterre.due_at')),
                ])
                ->action(fn(array $data, FinisterreTask $record, ViewFinisterreTask $livewire) => self::apply(
                    $livewire,
                    fn() => $record->update(['due_at' => $data['due_at'] ?? null])
                ))
                ->hidden($userIsReporterOnly),

            Action::make('quick_tags')
                ->badge()
                ->color('success')
                ->icon(Heroicon::OutlinedTag)
                ->label(fn(FinisterreTask $record) => $record->tags->isEmpty()
                    ? __('finisterre::finisterre.no_tags')
                    : $record->tags->map(fn($tag) => '#' . $tag->name)->implode(' '))
                ->tooltip(__('finisterre::finisterre.tags'))
                ->modalHeading(__('finisterre::finisterre.tags'))
                ->modalWidth(Width::Medium)
                ->fillForm(fn(FinisterreTask $record) => ['tags' => $record->tags->pluck('id')->all()])
                ->schema([
                    TagsSelect::make(),
                ])
                ->action(fn(array $data, FinisterreTask $record, ViewFinisterreTask $livewire) => self::apply(
                    $livewire,
                    fn() => TagsSelect::persist($record, $data['tags'] ?? [])
                )),

            Action::make('quick_attachments')
                ->badge()
                ->color('gray')
                ->icon(Heroicon::OutlinedPaperClip)
                ->label(fn(FinisterreTask $record) => (string)$record->getMedia('tasks')->count())
                ->tooltip(__('finisterre::finisterre.attachments'))
                ->modalHeading(__('finisterre::finisterre.attachments'))
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel(__('finisterre::finisterre.save'))
                // Filling the schema with an array — even an empty one — is what makes
                // Filament load the record's existing media into the field. Mounted
                // unfilled, the field would come up empty and its own save step would
                // then treat every attachment already on the task as removed.
                ->fillForm(fn(): array => [])
                ->schema([
                    SpatieMediaLibraryFileUpload::make('attachments')
                        ->hiddenLabel()
                        ->multiple()
                        ->disk(config('finisterre.attachments_disk') ?? 'public')
                        ->collection('tasks')
                        ->openable()
                        ->downloadable(),
                ])
                // The upload field writes the media itself while the modal is
                // submitted, so there is nothing left to persist here: this only
                // reloads what the page shows.
                ->action(fn(ViewFinisterreTask $livewire) => self::apply($livewire)),
        ];
    }

    protected static function apply(ViewFinisterreTask $livewire, ?Closure $persist = null): void
    {
        // Actions whose field persists on its own (the attachments upload) pass
        // nothing and only need the refresh below.
        if ($persist) {
            $persist();
        }

        $livewire->refreshRecord();

        Notification::make()
            ->title(__('finisterre::finisterre.quick_update.saved'))
            ->success()
            ->send();
    }
}
