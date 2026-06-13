<?php

namespace Buzkall\Finisterre\Filament\Pages;

use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Facades\Finisterre;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Buzkall\Finisterre\Filament\Widgets\FilterTasksWidget;
use Buzkall\Finisterre\Models\FinisterreTag;
use Buzkall\Finisterre\Models\FinisterreTask;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardPage;
use Relaticle\Flowforge\Column;

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
        // Route::has() guards against the board's slug colliding with an existing
        // route in the host panel: when that happens the board page route isn't
        // registered, so linking to it from the navigation would 500 the whole
        // panel. Hiding it instead degrades gracefully (re-run the installer to
        // pick a free slug).
        return config('finisterre.active', false)
            && Finisterre::get()->canViewAllTasks()
            && Route::has(static::getRouteName());
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

            Action::make('finisterreSettings')
                ->label(__('finisterre::finisterre.settings.nav_label'))
                ->icon(Heroicon::Cog6Tooth)
                ->color('gray')
                ->url(fn(): string => ManageFinisterreSettings::getUrl())
                ->visible(fn(): bool => Finisterre::get()->canConfigure()),
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
                                'tagNames'         => $record->tags->pluck('name'),
                                'mediaCount'       => $record->media_count ?? 0,
                                'commentsCount'    => $record->comments_count ?? 0,
                                'editUrl'          => FinisterreTaskResource::getUrl('edit', ['record' => $record->id]),
                                'updatedAt'        => $record->updated_at->diffForHumans(),
                                'hasChanges'       => (bool)$record->has_changes,
                            ]),
                    ])
            );
    }

    /**
     * Override flowforge's positioning so order_column stays a clean integer.
     *
     * Flowforge (Relaticle\Flowforge\Concerns\InteractsWithBoard) calculates a card's
     * position as the decimal midpoint between its two neighbours plus random jitter
     * (see Relaticle\Flowforge\Services\DecimalPosition), which produces long, ugly
     * decimals such as 63821.3847291500. That service is final readonly and is invoked
     * through hardcoded static calls, so the only durable seam without patching vendor
     * files is to override this protected trait method here. Instead of a midpoint we
     * renumber the whole target column sequentially (10, 20, 30, …): order_column stays
     * an integer, positions never collide, and flowforge's DecimalPosition /
     * PositionRebalancer are bypassed entirely. Columns hold few cards (<= 100), so the
     * extra updates are negligible.
     */
    protected function calculateAndUpdatePosition(
        Model $card,
        string $targetColumnId,
        ?string $afterCardId,
        ?string $beforeCardId
    ): string {
        $newPosition = '';

        DB::transaction(function() use ($card, $targetColumnId, $afterCardId, $beforeCardId, &$newPosition) {
            $board = $this->getBoard();
            $query = $board->getQuery();
            $positionField = $board->getPositionIdentifierAttribute();
            $columnField = $board->getColumnIdentifierAttribute();
            $keyName = $query->getModel()->getKeyName();

            // Lock every card in the target column so concurrent moves can't race.
            $columnCards = (clone $query)
                ->where($columnField, $targetColumnId)
                ->lockForUpdate()
                ->orderBy($positionField)
                ->orderBy($keyName)
                ->get();

            // Drop the moved card from the list (on a cross-column move it lives elsewhere).
            $others = $columnCards
                ->reject(fn($item) => (string)$item->getKey() === (string)$card->getKey())
                ->values();

            // Resolve where the moved card lands from its new neighbours.
            $insertIndex = match (true) {
                $afterCardId === null  => 0,
                $beforeCardId === null => $others->count(),
                default                => ($afterIndex = $others->search(
                    fn($item) => (string)$item->getKey() === $afterCardId
                )) === false ? $others->count() : $afterIndex + 1,
            };

            $ordered = $others->slice(0, $insertIndex)
                ->push($card)
                ->concat($others->slice($insertIndex))
                ->values();

            $columnValue = $this->resolveStatusValue($card, $columnField, $targetColumnId);

            // Renumber the column 10, 20, 30, … Sibling rows are rewritten too; the
            // FinisterreTask::saved() guard ignores order_column-only changes, so the
            // renumber never triggers assignee notifications.
            foreach ($ordered as $index => $item) {
                $position = ($index + 1) * 10;

                if ((string)$item->getKey() === (string)$card->getKey()) {
                    $card->update([$columnField => $columnValue, $positionField => $position]);
                    $newPosition = (string)$position;
                } elseif ((int)$item->getAttribute($positionField) !== $position) {
                    $item->update([$positionField => $position]);
                }
            }
        });

        return $newPosition;
    }

    protected function getFilteredQuery(): Builder
    {
        $userModel = app(config('finisterre.authenticatable'));

        return FinisterreTask::query()
            ->withCount([
                'comments' => fn($q) => $q->where(fn($q) => $q->whereNull('scheduled_for')->orWhereNotNull('sent_at')),
                'media',
                'taskChanges as has_changes' => fn($q) => $q->where('user_id', auth()->id()),
            ])
            ->addSelect([
                config('finisterre.table_name') . '.*',
                'assignee_name' => $userModel->newQuery()
                    ->select($userModel::getUserNameSelectExpression())
                    ->whereColumn($userModel->getTable() . '.id', config('finisterre.table_name') . '.assignee_id')
                    ->limit(1),
            ])
            ->when(
                $this->filters['filter_tags'] ?? null,
                fn($query, $tagIds) => $query->withAnyTags(FinisterreTag::findMany($tagIds))
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
