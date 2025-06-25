<?php

namespace Buzkall\Finisterre\Filament\Pages;

use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Filament\Forms\Components\SubtasksField;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\View;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;
use Rawilk\FilamentQuill\Filament\Forms\Components\QuillEditor;
use Spatie\Tags\Tag;

class TasksKanbanBoard extends KanbanBoard
{
    #[Url]
    public ?array $filters = null;

    protected static string $model = FinisterreTask::class;
    protected static string $statusEnum = TaskStatusEnum::class;
    protected string $editModalWidth = '4xl';
    protected string $editModalTitle = '';
    protected static string $view = 'finisterre::filament-kanban.kanban-board';
    protected static string $headerView = 'finisterre::filament-kanban.kanban-header';
    protected static string $recordView = 'finisterre::filament-kanban.kanban-record';
    protected $listeners = ['commentCreated' => '$refresh'];
    public bool $disableEditModal = true;

    public static function getSlug(): string
    {
        return config('finisterre.slug') ?? parent::getSlug();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public static function canAccess(): bool
    {
        return auth()->guard(config('finisterre.guard'))->user()->can('viewAny', FinisterreTask::class) &&
            config('finisterre.active');
    }

    public function getTitle(): string|Htmlable
    {
        return __('finisterre::finisterre.tasks');
    }

    public static function getNavigationLabel(): string
    {
        return __('finisterre::finisterre.tasks');
    }

    protected function getEditModalSaveButtonLabel(): string
    {
        return __('finisterre::finisterre.save');
    }

    protected function getEditModalCancelButtonLabel(): string
    {
        return __('finisterre::finisterre.cancel');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->model(FinisterreTask::class)
                ->label(__('finisterre::finisterre.create_task'))
                ->modalHeading(__('finisterre::finisterre.create_task'))
                ->form($this->getEditModalFormSchema(null))
                ->modalSubmitAction(fn($action) => $action->keyBindings(['mod+s'])) // save with mod+s
                ->createAnother(false)
                ->keyBindings(['mod+b']), // open create new with mod+b

            Action::make('filters')
                ->slideOver(false)
                ->modalWidth('2xl')
                ->keyBindings(['mod+shift+f']) // open filters with mod+shift+f
                ->label(__('finisterre::finisterre.filter.label'))
                ->icon('heroicon-m-funnel')
                ->badge(fn() => $this->filters ? count(array_filter($this->filters)) : null)
                ->badgeColor('warning')
                ->form(
                    [
                        CheckboxList::make('filter_tags')
                            ->label(__('finisterre::finisterre.tags'))
                            ->options(fn() => Tag::withType('tasks')->pluck('name', 'id'))
                            ->columns(),

                        TextInput::make('filter_text')
                            ->autofocus()
                            ->label(__('finisterre::finisterre.filter.text'))
                            ->helperText(__('finisterre::finisterre.filter.text_description')),

                        Select::make('filter_assignee')
                            ->label(__('finisterre::finisterre.filter.assignee'))
                            ->options(
                                fn() => FinisterreTask::query()
                                    ->distinct('assignee_id')
                                    ->with('assignee')
                                    ->get()
                                    ->pluck('assignee.name', 'assignee.id')
                            ),

                        Toggle::make('filter_show_archived')
                            ->label(__('finisterre::finisterre.filter.show_archived'))
                            ->helperText(__('finisterre::finisterre.filter.show_archived_description'))
                            ->default(false)
                            ->inline(false)
                            ->columnSpanFull()
                    ]
                )
                ->fillForm(fn() => $this->filters)
                ->action(fn($data) => $this->filters = $data)
                ->modalSubmitActionLabel(__('finisterre::finisterre.filter.cta'))
                ->extraModalFooterActions(fn() => [
                    Action::make('clear_filters')
                        ->label(__('finisterre::finisterre.filter.clear'))
                        ->color('warning')
                        ->visible(fn() => $this->filters)
                        ->action(fn() => $this->filters = null)->cancelParentActions()
                ])
        ];
    }

    protected function records(): Collection
    {
        return $this->getEloquentQuery()
            ->withCount(['comments', 'media'])
            ->when(method_exists(static::$model, 'scopeOrdered'), fn($query) => $query->ordered()) // @phpstan-ignore-line
            ->when(
                $this->filters['filter_tags'] ?? null,
                function($query, $tagIds) {
                    $query->withAnyTags(Tag::findMany($tagIds));

                    return $query;
                }
            )->when(
                $this->filters['filter_text'] ?? null,
                function($query, $text) {
                    $query->where(fn($query) => $query
                        ->where('title', 'like', "%$text%")
                        ->orWhere('description', 'like', "%$text%"));

                    return $query;
                }
            )->when(
                $this->filters['filter_assignee'] ?? null,
                function($query, $assigneeId) {
                    $query->where('assignee_id', $assigneeId);

                    return $query;
                }
            )
            ->when(
                $this->filters['filter_show_archived'] ?? false,
                fn($query) => $query,
                fn($query) => $query->notArchived()
            )
            ->get();
    }

    protected function getEditModalFormSchema(string|int|null $recordId): array
    {
        return [
            TextInput::make('title')
                ->label(__('finisterre::finisterre.title'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            QuillEditor::make('description')
                ->label(__('finisterre::finisterre.description'))
                ->hiddenLabel()
                ->fileAttachmentsVisibility('private')
                ->columnSpanFull(),

            Group::make([
                Select::make('status')
                    ->label(__('finisterre::finisterre.status'))
                    ->hiddenOn('create')
                    ->options(TaskStatusEnum::options())
                    ->default(TaskStatusEnum::Open)
                    ->required(),

                Select::make('priority')
                    ->label(__('finisterre::finisterre.priority'))
                    ->options(TaskPriorityEnum::class)
                    ->default(TaskPriorityEnum::Low)
                    ->required(),

                DatePicker::make('due_at')
                    ->label(__('finisterre::finisterre.due_at')),

                DatePicker::make('completed_at')
                    ->label(__('finisterre::finisterre.completed_at'))
                    ->hiddenOn('create')
                    ->disabled(),

                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label(__('finisterre::finisterre.attachments'))
                    ->multiple()
                    ->disk(config('finisterre.attachments_disk') ?? 'public')
                    ->collection('tasks')
                    ->openable()
                    ->downloadable(),

                Select::make('assignee_id')
                    ->label(__('finisterre::finisterre.assignee_id'))
                    ->required()
                    ->relationship(
                        'assignee',
                        config('finisterre.authenticatable_attribute'),
                        fn($query) => $query
                            ->when(
                                config('finisterre.authenticatable_filter_column'),
                                fn($query) => $query->where(config('finisterre.authenticatable_filter_column'), config('finisterre.authenticatable_filter_value'))
                            )
                            ->when(
                                Schema::hasColumn(config('finisterre.authenticatable_table_name'), 'active'),
                                fn($query) => $query->where('active', true)
                            )
                    )
                    ->default(config('finisterre.fallback_notifiable_id')),

                SpatieTagsInput::make('tags')
                    ->label(__('finisterre::finisterre.tags'))
                    ->type('tasks'),

                SubtasksField::make('subtasks')
                    ->label(__('finisterre::finisterre.subtasks.label'))
                    ->columnSpanFull(),

                Placeholder::make('dates')
                    ->hiddenLabel()
                    ->hiddenOn('create')
                    ->hintIcon('heroicon-o-clock')
                    ->hint(fn($record) => new HtmlString(
                        __('finisterre::finisterre.created_at') . ': ' .
                        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . // fake alignment
                        $record?->created_at->format('d/m/y H:i:s') .
                        '<br />' .
                        __('finisterre::finisterre.updated_at') . ': ' . $record?->updated_at->format('d/m/y H:i:s')
                    ))->columnSpanFull(),

                // Add submit buttons above the comments
                Actions::make([
                    FormAction::make('submit')
                        ->label(self::getEditModalSaveButtonLabel())
                        ->submit('save')
                        ->keyBindings(['mod+s'])

                ])->columnSpanFull()
                    ->alignEnd()
                    ->hiddenOn('create'),

                View::make('finisterre::comments.view')
                    ->hiddenOn('create')
                    ->columnSpanFull()
            ])->columns()
        ];
    }
}
