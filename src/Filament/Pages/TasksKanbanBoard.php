<?php

namespace Buzkall\Finisterre\Filament\Pages;

use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;
use Override;
use Spatie\Tags\Tag;

class TasksKanbanBoard extends KanbanBoard
{
    //use HasFilters;

    protected static string $model = FinisterreTask::class;
    protected static string $statusEnum = TaskStatusEnum::class;
    protected string $editModalWidth = '3xl';
    protected string $editModalTitle = '';
    protected static string $view = 'finisterre::filament-kanban.kanban-board';
    protected static string $headerView = 'finisterre::filament-kanban.kanban-header';
    protected static string $recordView = 'finisterre::filament-kanban.kanban-record';

    #[Url]
    public ?array $filters = null;

    public static function getSlug(): string
    {
        return config('finisterre.slug') ?? parent::getSlug();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('finisterre.active') ?? false;
    }

    public static function canAccess(): bool
    {
        return config('finisterre.active') ?? false;
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
                ->label(__('filament-panels::pages/dashboard.actions.filter.label'))
                ->icon('heroicon-m-funnel')
                ->badge(fn() => $this->filters ? count($this->filters) : null)
                ->badgeColor('warning')
                ->form(
                    [
                        Select::make('filter_tags')
                            ->multiple()
                            ->options(Tag::withType('tasks')->get()->pluck('name', 'name')),

                        //TextInput::make('test')
                    ]
                )
                ->action(fn($data) => $this->filters = $data),
        ];
    }

    #[Override]
    protected function records(): Collection
    {
        return $this->getEloquentQuery()
            ->when(method_exists(static::$model, 'scopeOrdered'), fn($query) => $query->ordered())
            ->when(
                $this->filters['filter_tags'] ?? null,
                function($query, $tags) {
                    ray($tags);

                    foreach ($tags as $tag) {
                        $query->orWhereHas(
                            'tags',
                            fn($query) => $query->whereJsonContains('name->' . app()->getLocale(), $tag)
                        );
                    }

                    return $query;
                }
            )
            ->get();
    }

    protected function getEditModalFormSchema(?int $recordId): array
    {
        return [
            TextInput::make('title')
                ->label(__('finisterre::finisterre.title'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            RichEditor::make('description')
                ->label(__('finisterre::finisterre.description'))
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
                    // ->disk('private') TODO
                    ->collection('tasks'),

                /*TextInput::make('user_id')
                    ->label(__('finisterre::finisterre.user_id'))
                    ->numeric(),*/

                Select::make('assignee_id')
                    ->label(__('finisterre::finisterre.assignee_id'))
                    ->relationship('assignee', 'name'), // TODO: configure filter

                SpatieTagsInput::make('tags')
                    ->label(__('finisterre::finisterre.tags'))
                    ->type('tasks'),

                Placeholder::make('dates')
                    ->hiddenLabel()
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
                ])->columnSpanFull()
                    ->alignEnd(),

                View::make('finisterre::comments.view')
                    ->hiddenOn('create')
                    ->columnSpanFull()
            ])->columns()
        ];
    }
}
