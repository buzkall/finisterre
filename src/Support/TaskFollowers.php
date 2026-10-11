<?php

namespace Arzcode\Finisterre\Support;

use ArrayObject;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\HasMedia;
use Throwable;

/**
 * Who follows a task: the people notified of its new comments, and the ones a
 * task stays visible to when they cannot see all tasks.
 */
class TaskFollowers
{
    /** How many people the selects offer before anything is typed. */
    private const int OPTIONS_LIMIT = 50;

    private const string MEMO = 'finisterre.followers.memo';

    /** Only ever remembered as true: a table that is there stays there. */
    protected static bool $tableExists = false;

    public static function table(): string
    {
        return Typed::string(config('finisterre.followers_table_name'), 'finisterre_task_followers');
    }

    /**
     * False until the host has run the migration that creates the table. Until then
     * nobody follows anything, and comments go to the task's creator and assignee.
     */
    public static function available(): bool
    {
        if (self::$tableExists) {
            return true;
        }

        // A missing table is asked about once per request or job, not once per card
        // and policy check: a host that has not migrated yet would pay for it on
        // every page. It is not remembered for longer, so the migration is noticed.
        $memo = self::memo();

        if (! isset($memo['missing'])) {
            self::$tableExists = rescue(fn(): bool => Schema::hasTable(self::table()), false, report: false);
            $memo['missing'] = ! self::$tableExists;
        }

        return self::$tableExists;
    }

    /** Asks again: after the migration ran, or when a test takes the table away. */
    public static function forgetTable(): void
    {
        self::$tableExists = false;
        self::memo()->exchangeArray([]);
    }

    /**
     * What is remembered for the length of one request or queued job, and no
     * longer: the container drops scoped instances between them.
     *
     * @return ArrayObject<string, mixed>
     */
    protected static function memo(): ArrayObject
    {
        if (! app()->bound(self::MEMO)) {
            app()->scoped(self::MEMO, fn(): ArrayObject => new ArrayObject);
        }

        /** @var ArrayObject<string, mixed> */
        return app(self::MEMO);
    }

    /**
     * @param  iterable<mixed>  $userIds
     */
    public static function add(FinisterreTask $task, iterable $userIds): void
    {
        $ids = self::normalize($userIds);

        if ($ids === [] || ! self::available()) {
            return;
        }

        // Written through the query builder, so following a task that is already
        // followed is not an error and the task's updated_at stays as it was.
        DB::table(self::table())->insertOrIgnore(array_map(fn(int $id): array => [
            'task_id'    => $task->getKey(),
            'user_id'    => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $ids));

        self::memo()->exchangeArray([]);
        $task->unsetRelation('followers');
    }

    /**
     * @param  iterable<mixed>  $userIds
     */
    public static function remove(FinisterreTask $task, iterable $userIds): void
    {
        $ids = self::normalize($userIds);

        if ($ids === [] || ! self::available()) {
            return;
        }

        DB::table(self::table())->where('task_id', $task->getKey())->whereIn('user_id', $ids)->delete();

        self::memo()->exchangeArray([]);
        $task->unsetRelation('followers');
    }

    /**
     * @return list<int>
     */
    public static function ids(FinisterreTask $task): array
    {
        if (! self::available()) {
            return self::normalize([$task->creator_id, $task->assignee_id]);
        }

        return self::normalize(DB::table(self::table())
            ->where('task_id', $task->getKey())
            ->orderBy('id')
            ->pluck('user_id'));
    }

    /**
     * The followers themselves, in the order they started following, with what
     * their avatar needs.
     *
     * @return Collection<int, Model>
     */
    public static function users(FinisterreTask $task): Collection
    {
        return self::usersById(self::ids($task));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Model>
     */
    public static function usersById(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $query = UserModel::class()::query();

        // Hosts whose user model answers getFilamentAvatarUrl() from a media
        // library would query that relation once per user unless it comes along.
        return $query
            ->when($query->getModel() instanceof HasMedia, fn(Builder $query) => $query->with('media'))
            ->findMany($ids)
            ->sortBy(fn(Model $user) => array_search(Typed::int($user->getKey()), $ids, true))
            ->values();
    }

    /**
     * Until the table exists, the creator and the assignee count as following, the
     * same two people ids() answers with: they keep opening the task their
     * notifications lead to.
     */
    public static function follows(FinisterreTask $task, mixed $userId): bool
    {
        $userId = Typed::nullableInt($userId);

        if ($userId === null) {
            return false;
        }

        if (! self::available()) {
            return in_array($userId, self::ids($task), true);
        }

        return DB::table(self::table())->where('task_id', $task->getKey())->where('user_id', $userId)->exists();
    }

    /**
     * Asked by the policy and the navigation on every page, so the answer is kept
     * for the request.
     */
    public static function followsAny(mixed $userId): bool
    {
        $userId = Typed::nullableInt($userId);

        if ($userId === null) {
            return false;
        }

        $memo = self::memo();
        $key = 'any.' . $userId;

        $memo[$key] ??= self::available()
            ? DB::table(self::table())->where('user_id', $userId)->exists()
            : self::tasksInvolving($userId)->exists();

        return (bool)$memo[$key];
    }

    /**
     * The ids of the tasks a user follows, to narrow a task query with.
     */
    public static function taskIds(mixed $userId): QueryBuilder
    {
        return self::available()
            ? DB::table(self::table())->select('task_id')->where('user_id', $userId)
            : self::tasksInvolving($userId)->select('id');
    }

    /**
     * The tasks somebody created or is assigned: what they follow while the
     * followers table is not there yet.
     */
    protected static function tasksInvolving(mixed $userId): QueryBuilder
    {
        return DB::table(Typed::string(config('finisterre.table_name'), 'finisterre_tasks'))
            ->where(fn(QueryBuilder $query) => $query->where('creator_id', $userId)->orWhere('assignee_id', $userId));
    }

    /**
     * The users who can be made a follower: every active one, unless the host
     * narrows it with FinisterrePlugin::modifyFollowersQueryUsing(). Wider than the
     * users a task can be assigned to on purpose, since following is how a task is
     * shown to somebody outside that group.
     *
     * @return Builder<Model>
     */
    public static function candidatesQuery(): Builder
    {
        $query = UserModel::class()::query();
        $model = $query->getModel();

        // The scope narrows the builder in place.
        if (method_exists($model, 'scopeUserIsActive')) {
            $model->scopeUserIsActive($query);
        }

        try {
            FinisterrePlugin::get()->applyFollowersQuery($query);
        } catch (Throwable) {
            // No panel with the plugin (a queued job, a reply by email): nothing to narrow with.
        }

        return $query;
    }

    /**
     * Options for a select of people to add, keyed by id.
     *
     * @param  list<int>  $except
     * @return array<int|string, string>
     */
    public static function options(?string $search = null, array $except = []): array
    {
        $columns = Typed::strings(Arr::wrap(config('finisterre.authenticatable_attribute', 'name') ?? 'name'));

        return self::candidatesQuery()
            ->when(filled($search), fn(Builder $query) => $query->where(function(Builder $query) use ($columns, $search): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%' . $search . '%');
                }
            }))
            ->when($except !== [], fn(Builder $query) => $query->whereKeyNot($except))
            ->orderBy($columns[0] ?? 'name')
            ->limit(self::OPTIONS_LIMIT)
            ->get()
            ->mapWithKeys(fn(Model $user): array => [Typed::string($user->getKey()) => UserModel::displayName($user)])
            ->all();
    }

    /**
     * Whether anybody is left to add once these people are left out.
     *
     * @param  list<int>  $except
     */
    public static function hasCandidates(array $except = []): bool
    {
        return self::candidatesQuery()
            ->when($except !== [], fn(Builder $query) => $query->whereKeyNot($except))
            ->exists();
    }

    /**
     * The given ids that are users who can be made a follower.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function onlyCandidates(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $query = self::candidatesQuery()->whereKey($ids);

        return self::normalize($query->pluck($query->getModel()->getQualifiedKeyName()));
    }

    /**
     * Ids as they come from a form, a model or the browser, as distinct ints.
     *
     * @param  iterable<mixed>  $userIds
     * @return list<int>
     */
    public static function normalize(iterable $userIds): array
    {
        $ids = [];

        foreach ($userIds as $userId) {
            $id = Typed::nullableInt($userId);

            if ($id !== null && $id !== 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public static function initials(?string $name): string
    {
        return collect(preg_split('/\s+/', trim((string)$name)) ?: [])
            ->filter()
            ->map(fn(string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
