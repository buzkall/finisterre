<?php

namespace Buzkall\Finisterre\Filament\Pages;

use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Facades\Finisterre;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Buzkall\Finisterre\Filament\Widgets\FilterTasksWidget;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\CreateAction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardPage;
use Relaticle\Flowforge\Column;
use Spatie\Tags\Tag;

class TasksKanbanBoard extends BoardPage
{
    #[Url]
    public ?array $filters = null;

    protected static string|null|\BackedEnum $navigationIcon = Heroicon::OutlinedExclamationTriangle;
    protected $listeners = [
        'commentCreated' => '$refresh',
    ];

    public static function getSlug(?Panel $panel = null): string
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

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('finisterre::finisterre.create_task'))
                ->url(FinisterreTaskResource::getUrl('create'))
                ->createAnother(false)
                ->keyBindings(['mod+b']),
        ];
    }

    #[On('filtersUpdated')]
    public function updateFilters(array $filters): void
    {
        $this->filters = $filters;
    }

    public function board(Board $board): Board
    {
        return $board
            ->query(fn() => $this->getFilteredQuery())
            ->recordTitleAttribute('title')
            ->columnIdentifier('status')
            ->positionIdentifier('order_column')
            ->columns($this->getColumns())
            ->cardSchema(
                fn($schema) => $schema
                    ->components([
                        ViewEntry::make('card_info')
                            ->view('finisterre::tasks.task-card-info')
                            ->viewData(fn(FinisterreTask $record) => [
                                'assignee'         => $record->assignee_name,
                                'assigneeInitials' => self::getInitials($record->assignee_name),
                                'priority'         => $record->priority->getLabel(),
                                'priorityColor'    => $record->priority->getColor(),
                                'tagName'          => $record->tags->first()?->name,
                                'mediaCount'       => $record->media_count ?? 0,
                                'commentsCount'    => $record->comments_count ?? 0,
                                'editUrl'          => FinisterreTaskResource::getUrl('edit', ['record' => $record->id]),
                                'updatedAt'        => $record->updated_at?->format('d/m/y H:i:s'),
                            ]),
                    ])
            );
    }

    protected function getFilteredQuery(): Builder
    {
        $userModel = app(config('finisterre.authenticatable'));

        return FinisterreTask::query()
            ->withCount(['comments', 'media'])
            ->addSelect([
                config('finisterre.table_name') . '.*',
                'assignee_name' => $userModel->newQuery()
                    ->select('name')
                    ->whereColumn($userModel->getTable() . '.id', config('finisterre.table_name') . '.assignee_id')
                    ->limit(1),
            ])
            ->when(
                $this->filters['filter_tags'] ?? null,
                fn($query, $tagIds) => $query->withAnyTags(Tag::findMany($tagIds))
            )
            ->when(
                $this->filters['filter_text'] ?? null,
                fn($query, $text) => $query->where(fn($query) => $query
                    ->where('title', 'like', "%$text%")
                    ->orWhere('description', 'like', "%$text%"))
            )
            ->when(
                $this->filters['filter_assignee'] ?? null,
                fn($query, $assigneeId) => $query->where('assignee_id', $assigneeId)
            )
            ->when(
                $this->filters['filter_show_archived'] ?? false,
                fn($query) => $query,
                fn($query) => $query->notArchived()
            );
    }

    protected function getColumns(): array
    {
        $hiddenStatuses = config('finisterre.hidden_statuses', []);

        return collect(TaskStatusEnum::cases())
            ->reject(fn($status) => in_array($status->value, $hiddenStatuses))
            ->map(
                fn(TaskStatusEnum $status) => Column::make($status->value)
                    ->label($status->getLabel())
                    ->color($status->getColor())
            )
            ->values()
            ->toArray();
    }

    protected static function getInitials(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return collect(explode(' ', $name))
            ->map(fn(string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FilterTasksWidget::class,
        ];
    }
}
