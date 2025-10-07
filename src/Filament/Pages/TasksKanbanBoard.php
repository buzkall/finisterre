<?php

namespace Buzkall\Finisterre\Filament\Pages;

use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Facades\Finisterre;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\CreateAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Mokhosh\FilamentKanban\Pages\KanbanBoard;
use Spatie\Tags\Tag;

class TasksKanbanBoard extends KanbanBoard implements HasForms
{
    use InteractsWithForms;

    #[Url]
    public ?array $filters = null;

    protected static string $model = FinisterreTask::class;
    protected static string $statusEnum = TaskStatusEnum::class;
    protected string $editModalWidth = '4xl';
    protected string $editModalTitle = '';
    protected static string $view = 'finisterre::filament-kanban.kanban-board';
    protected static string $headerView = 'finisterre::filament-kanban.kanban-header';
    protected static string $recordView = 'finisterre::filament-kanban.kanban-record';
    protected $listeners = [
        'commentCreated' => '$refresh',
        'filtersUpdated' => 'updateFilters',
    ];
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
        ];
    }

    public function updateFilters(array $filters): void
    {
        // this event is called from the FilterTasks component
        $this->filters = $filters;
        $this->dispatch('$refresh');
    }

    protected function records(): Collection
    {
        return $this->getEloquentQuery()
            ->withCount(['comments', 'media'])
            ->with('taskChanges')
            ->when(method_exists(static::$model, 'scopeOrdered'), fn($query) => $query->ordered()) // @phpstan-ignore-line
            ->when(
                $this->filters['filter_tags'] ?? null,
                fn($query, $tagIds) => $query->withAnyTags(Tag::findMany($tagIds))
            )->when(
                $this->filters['filter_text'] ?? null,
                fn($query, $text) => $query->where(fn($query) => $query
                    ->where('title', 'like', "%$text%")
                    ->orWhere('description', 'like', "%$text%"))
            )->when(
                $this->filters['filter_assignee'] ?? null,
                fn($query, $assigneeId) => $query->where('assignee_id', $assigneeId)
            )
            ->when(
                $this->filters['filter_show_archived'] ?? false,
                fn($query) => $query,
                fn($query) => $query->notArchived()
            )
            ->get();
    }
}
