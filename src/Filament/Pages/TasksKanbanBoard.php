<?php

namespace Arzcode\Finisterre\Filament\Pages;

use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Facades\Finisterre;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Filament\Widgets\FilterTasksWidget;
use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Observers\FinisterreTaskObserver;
use Arzcode\Finisterre\Support\UserAvatar;
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
    /**
     * Deliberately not named `filters`: Filament's Page passes a page property
     * called `filters` to every widget as an extra `pageFilters` mount param
     * (Filament\Pages\Page::getWidgetsSchemaComponents()). FilterTasksWidget has
     * no such property, so on a fresh page load — coming back to the board with
     * the filters still in the URL — the array ended up as an HTML attribute of
     * the widget's lazy-loading placeholder and blew up with "trim(): Argument
     * #1 ($string) must be of type string, array given". The name also has to
     * stay clear of flowforge's `#[Url(as: 'filters')] $tableFilters`.
     */
    #[Url]
    public ?array $taskFilters = null;

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
        $this->taskFilters = $filters;
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
                            ->viewData(function(FinisterreTask $record): array {
                                // The creator circle only earns its space on the card when
                                // somebody else is doing the work; on a self-assigned task it
                                // would just repeat the assignee.
                                $creatorId = $record->creator_id === $record->assignee_id
                                    ? null
                                    : $record->creator_id;

                                $creatorName = $creatorId === null ? null : $record->creator_name;

                                return [
                                    'assignee'         => $record->assignee_name,
                                    'assigneeInitials' => self::getInitials($record->assignee_name),
                                    'assigneeAvatar'   => UserAvatar::url($this->cardUser($record->assignee_id)),
                                    'creator'          => $creatorName,
                                    'creatorInitials'  => self::getInitials($creatorName),
                                    'creatorAvatar'    => UserAvatar::url($this->cardUser($creatorId)),
                                    'priority'         => $record->priority->getLabel(),
                                    'priorityColor'    => $record->priority->getColor(),
                                    'tagNames'         => $record->tags->pluck('name'),
                                    'mediaCount'       => $record->media_count ?? 0,
                                    'commentsCount'    => $record->comments_count ?? 0,
                                    'subtasksCount'    => $record->subtasks_count ?? 0,
                                    'subtasksDone'     => $record->completed_subtasks_count ?? 0,
                                    'viewUrl'          => FinisterreTaskResource::getUrl('view', ['record' => $record->id]),
                                    'updatedAt'        => $record->updated_at->diffForHumans(),
                                    'hasChanges'       => (bool)$record->has_changes,
                                ];
                            }),
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

        // The board decides where a dragged card lands, so the observer's "a task set
        // to done goes first" rule must not override the position of the drop.
        FinisterreTaskObserver::withoutRepositioning(function() use ($card, $targetColumnId, $afterCardId, $beforeCardId, &$newPosition) {
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
                // renumber never triggers assignee notifications. Timestamps are disabled
                // while renumbering: a position-only write is not a change to the task, and
                // bumping updated_at would make every card in the target column look edited.
                foreach ($ordered as $index => $item) {
                    $position = ($index + 1) * 10;

                    if ((string)$item->getKey() === (string)$card->getKey()) {
                        $card->fill([$columnField => $columnValue, $positionField => $position]);

                        // Only a real column change is worth a new updated_at; a drag inside
                        // the same column moves nothing but the position.
                        $card->timestamps = $card->isDirty($columnField);
                        $card->save();
                        $card->timestamps = true;

                        $newPosition = (string)$position;
                    } elseif ((int)$item->getAttribute($positionField) !== $position) {
                        $item->timestamps = false;
                        $item->update([$positionField => $position]);
                        $item->timestamps = true;
                    }
                }
            });
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
                'subtasks',
                'subtasks as completed_subtasks_count' => fn($q) => $q->where('completed', true),
                'taskChanges as has_changes'           => fn($q) => $q->where('user_id', auth()->id()),
            ])
            ->addSelect([
                config('finisterre.table_name') . '.*',
                'assignee_name' => $userModel->newQuery()
                    ->select($userModel::getUserNameSelectExpression())
                    ->whereColumn($userModel->getTable() . '.id', config('finisterre.table_name') . '.assignee_id')
                    ->limit(1),
                'creator_name' => $userModel->newQuery()
                    ->select($userModel::getUserNameSelectExpression())
                    ->whereColumn($userModel->getTable() . '.id', config('finisterre.table_name') . '.creator_id')
                    ->limit(1),
            ])
            ->when(
                $this->taskFilters['filter_tags'] ?? null,
                fn($query, $tagIds) => $query->withAnyTags(FinisterreTag::findMany($tagIds))
            )
            ->when(
                $this->taskFilters['filter_text'] ?? null,
                fn($query, $text) => $query->where(fn($query) => $query
                    ->where('title', 'like', "%$text%")
                    ->orWhere('description', 'like', "%$text%"))
            )
            ->when(
                $this->taskFilters['filter_assignee'] ?? null,
                fn($query, $assigneeId) => $query->where('assignee_id', $assigneeId)
            )
            ->when(
                // Livewire writes the toggle to the query string as the *string*
                // "false", which is truthy, so a reload would silently unhide the
                // archived tasks without the cast.
                filter_var($this->taskFilters['filter_show_archived'] ?? false, FILTER_VALIDATE_BOOLEAN),
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

    /**
     * The user models the visible cards need, resolved once per render.
     *
     * The board query cannot carry them: flowforge re-queries the cards through
     * its own clones (and through Filament's table query once the board is
     * filterable), so an eager load on this page's query is not guaranteed to
     * survive, and reading $record->assignee per card is a plain N+1. A card
     * only needs the model for its avatar, and a board shows a handful of
     * distinct people, so one keyed lookup each is bounded and shared.
     *
     * @var array<int, ?Model>
     */
    protected array $cardUsers = [];

    protected function cardUser(?int $id): ?Model
    {
        if ($id === null) {
            return null;
        }

        if (! array_key_exists($id, $this->cardUsers)) {
            $this->cardUsers[$id] = app(config('finisterre.authenticatable'))->newQuery()->find($id);
        }

        return $this->cardUsers[$id];
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
