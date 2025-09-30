<?php

namespace Buzkall\Finisterre\Filament\Pages;

use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Facades\Finisterre;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;
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
        return Finisterre::get()->canViewAllTasks();
    }

    public static function shouldRegisterSpotlight(): bool
    {
        return false;
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
                ->label(__('finisterre::finisterre.create_task'))
                ->url(FinisterreTaskResource::getUrl('create'))
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
}
