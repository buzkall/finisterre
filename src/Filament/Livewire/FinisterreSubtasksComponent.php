<?php

namespace Arzcode\Finisterre\Filament\Livewire;

use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreSubtask;
use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Subtask checklist with instant persistence: every tick, rename, delete and
 * reorder is written straight away, so nothing depends on saving the task form.
 *
 * Every mutating method re-authorises. A Livewire component is a public
 * endpoint — the signed snapshot stops `$record` being swapped, but the ids
 * passed into these methods are attacker-controlled, so each one is resolved
 * through the task's own relation rather than looked up globally.
 */
class FinisterreSubtasksComponent extends Component
{
    /** Gap between order values, so a future insert-between has room. */
    private const ORDER_STEP = 10;

    public ?FinisterreTask $record = null;

    /**
     * Keyed by "subtask-{id}", never by position: a positional key would shift
     * every row below a deletion onto a different state path, and Livewire's
     * DOM morph would leave the old inputs bound to the wrong data.
     *
     * The prefix keeps the keys non-numeric on purpose — JS objects reorder
     * integer-like keys numerically, which would silently re-sort the list.
     *
     * @var array<string, array{id: int, title: string, completed: bool}>
     */
    public array $subtasks = [];

    public string $newTitle = '';

    public function mount(): void
    {
        $this->loadSubtasks();
    }

    public function canManage(): bool
    {
        if ($this->record === null) {
            return false;
        }

        // Reporters only file tasks; the checklist is for whoever works them.
        // Resolved defensively: FinisterrePlugin::get() needs a booted panel,
        // and this also runs from tests and queued contexts where none exists.
        try {
            if (FinisterrePlugin::get()->canViewOnlyTheirTasks()) {
                return false;
            }
        } catch (\Throwable) {
            // No panel — fall through to the policy check below.
        }

        return auth()->user()?->can('update', $this->record) ?? false;
    }

    public function add(): void
    {
        $this->guard();

        $title = trim($this->newTitle);

        // The input caps length client-side; server-side an out-of-range value
        // is simply not stored, matching how a blurred title is handled below.
        if ($title === '' || mb_strlen($title) > 255) {
            return;
        }

        $this->record->subtasks()->create([
            'title'        => $title,
            'completed'    => false,
            'order_column' => ((int)$this->record->subtasks()->max('order_column')) + self::ORDER_STEP,
        ]);

        $this->newTitle = '';
        $this->loadSubtasks();
        $this->dispatchCounts();
    }

    /**
     * Fires for `subtasks.{index}.completed` and `subtasks.{index}.title`, so a
     * tick or a blurred rename persists through one hook.
     */
    public function updatedSubtasks(mixed $value, string $key): void
    {
        $this->guard();

        [$rowKey, $field] = array_pad(explode('.', $key, 2), 2, null);

        if (! in_array($field, ['completed', 'title'], true)) {
            return;
        }

        $subtask = $this->subtaskAt($rowKey);

        if (! $subtask) {
            return;
        }

        if ($field === 'completed') {
            $subtask->update(['completed' => (bool)$value]);
            $this->loadSubtasks();
            $this->dispatchCounts();

            return;
        }

        $title = trim((string)$value);

        // An emptied title is a slip, not a delete — put the stored one back.
        if ($title === '' || mb_strlen($title) > 255) {
            $this->loadSubtasks();

            return;
        }

        $subtask->update(['title' => $title]);
        $this->loadSubtasks();
    }

    public function delete(string $rowKey): void
    {
        $this->guard();

        $this->subtaskAt($rowKey)?->delete();

        $this->loadSubtasks();
        $this->dispatchCounts();
    }

    /**
     * @param  array<int, int|string>  $ids  subtask ids in their new order
     */
    public function reorder(array $ids): void
    {
        $this->guard();

        // Only ids that really belong to this task, in the order given.
        $owned = $this->record->subtasks()->pluck('id')->all();
        $ordered = array_values(array_filter(
            array_map('intval', $ids),
            fn(int $id) => in_array($id, $owned, true)
        ));

        DB::transaction(function() use ($ordered) {
            foreach ($ordered as $position => $id) {
                $this->record->subtasks()
                    ->whereKey($id)
                    ->update(['order_column' => ($position + 1) * self::ORDER_STEP]);
            }

            // These are query-builder updates, so no model events fire and
            // FinisterreSubtask::$touches never bumps the task. Do it here, or a
            // drag would be the only edit that leaves the board's "updated X
            // ago" stale. Touching only moves updated_at, which
            // FinisterreTaskObserver::saved() ignores — no assignee is notified.
            if ($ordered !== []) {
                $this->record->touch();
            }
        });

        $this->loadSubtasks();
    }

    public static function rowKey(int $id): string
    {
        return 'subtask-' . $id;
    }

    /** @return array{done: int, total: int} */
    public function counts(): array
    {
        $subtasks = collect($this->subtasks);

        return [
            'done'  => $subtasks->where('completed', true)->count(),
            'total' => $subtasks->count(),
        ];
    }

    /**
     * The done/total badge lives in the header of the section that wraps this
     * component, which a nested Livewire round trip does not re-render. Pushing
     * the counts out as a browser event lets that badge follow along.
     */
    public function dispatchCounts(): void
    {
        $this->dispatch('finisterre-subtasks-updated', ...$this->counts());
    }

    public function render(): View
    {
        return view('finisterre::subtasks.subtasks');
    }

    protected function guard(): void
    {
        abort_unless($this->canManage(), 403, __('finisterre::finisterre.subtasks.forbidden'));
    }

    /** Resolve a row through the bound array, scoped to this task's own rows. */
    protected function subtaskAt(?string $rowKey): ?FinisterreSubtask
    {
        $id = $this->subtasks[$rowKey]['id'] ?? null;

        if ($id === null) {
            return null;
        }

        return $this->record->subtasks()->whereKey($id)->first();
    }

    protected function loadSubtasks(): void
    {
        $this->subtasks = $this->record
            ? $this->record->subtasks()->get()
                ->mapWithKeys(fn(FinisterreSubtask $subtask): array => [
                    self::rowKey($subtask->id) => [
                        'id'        => $subtask->id,
                        'title'     => $subtask->title,
                        'completed' => $subtask->completed,
                    ],
                ])
                ->all()
            : [];
    }
}
