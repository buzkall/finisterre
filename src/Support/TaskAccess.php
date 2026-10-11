<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Which tasks the signed-in user gets, and what they may do with them.
 *
 * Users who can view all tasks get every one. Everybody else gets the tasks they
 * follow, to read and comment on. The task assigned to them stays theirs to edit,
 * and the users limited to their own tasks also get the ones they created, to edit
 * as well.
 *
 * Decided per call rather than once at boot, because the model boots wherever it
 * is first touched, and that is not always a panel carrying the plugin. The media
 * observer builds a task on every file saved anywhere in the host, so a host
 * panel without Finisterre (a website editor, say) booted the model there and
 * FinisterrePlugin::get() threw "Plugin [finisterre] is not registered". Outside a
 * panel with the plugin nobody is restricted.
 */
class TaskAccess
{
    /**
     * Whether the signed-in user only gets the tasks they follow (and created).
     * A request without a user is not restricted: there is nobody to follow
     * anything, and a reply by email has to find its task.
     */
    public static function isRestricted(): bool
    {
        $plugin = self::plugin();

        if (! $plugin instanceof FinisterrePlugin) {
            return false;
        }

        return self::safely(fn(): bool => $plugin->canViewOnlyTheirTasks() || (auth()->check() && ! $plugin->canViewAllTasks()));
    }

    /**
     * Whether the tasks the signed-in user created are theirs to see and edit.
     */
    public static function ownsCreatedTasks(): bool
    {
        $plugin = self::plugin();

        return $plugin instanceof FinisterrePlugin && self::safely($plugin->canViewOnlyTheirTasks(...));
    }

    /**
     * @param  Builder<covariant FinisterreTask>  $query
     */
    public static function scope(Builder $query): void
    {
        if (! self::isRestricted()) {
            return;
        }

        $userId = auth()->id();

        $query->where(fn(Builder $query) => $query
            ->whereIn($query->qualifyColumn($query->getModel()->getKeyName()), TaskFollowers::taskIds($userId))
            ->when(
                self::ownsCreatedTasks(),
                fn(Builder $query) => $query->orWhere($query->qualifyColumn('creator_id'), $userId)
            ));
    }

    public static function canView(Authenticatable $user, FinisterreTask $task): bool
    {
        if (! self::isRestricted()) {
            return true;
        }

        return self::created($user, $task) || TaskFollowers::follows($task, $user->getAuthIdentifier());
    }

    /**
     * A task somebody only follows is theirs to read and comment on, not to change.
     * The one it is assigned to does the work, so they keep editing it.
     */
    public static function canUpdate(Authenticatable $user, FinisterreTask $task): bool
    {
        return ! self::isRestricted() || self::created($user, $task) || self::assigned($user, $task);
    }

    protected static function assigned(Authenticatable $user, FinisterreTask $task): bool
    {
        return $task->assignee_id !== null
            && $task->assignee_id === Typed::nullableInt($user->getAuthIdentifier());
    }

    protected static function created(Authenticatable $user, FinisterreTask $task): bool
    {
        return self::ownsCreatedTasks()
            && $task->creator_id !== null
            && $task->creator_id === Typed::nullableInt($user->getAuthIdentifier());
    }

    protected static function plugin(): ?FinisterrePlugin
    {
        if (! app()->bound('filament')) {
            return null;
        }

        try {
            return FinisterrePlugin::get();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  callable(): bool  $check
     */
    protected static function safely(callable $check): bool
    {
        try {
            return $check();
        } catch (Throwable) {
            return false;
        }
    }
}
