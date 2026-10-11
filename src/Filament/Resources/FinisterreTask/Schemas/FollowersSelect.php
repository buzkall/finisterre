<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas;

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\TaskFollowers;
use Arzcode\Finisterre\Support\Typed;
use Arzcode\Finisterre\Support\UserModel;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

/**
 * The people picker shared by the followers of a task and the recipients of a
 * comment. It offers the first few candidates and searches for the rest, since
 * a host may keep far more users than fit in a list.
 */
class FollowersSelect
{
    /**
     * @param  list<int>|Closure(): array<int>  $except  People left out of the options, read when they are asked for
     */
    public static function make(string $name = 'followers', array|Closure $except = []): Select
    {
        $except = fn(): array => TaskFollowers::normalize($except instanceof Closure ? $except() : $except);

        return Select::make($name)
            ->label(__('finisterre::finisterre.followers.label'))
            ->placeholder(__('finisterre::finisterre.followers.add'))
            ->searchable()
            ->options(fn(): array => TaskFollowers::options(except: $except()))
            ->getSearchResultsUsing(fn(string $search): array => TaskFollowers::options($search, $except()))
            // Whoever already follows keeps their name in the field, even when the
            // host no longer offers them as a candidate.
            ->getOptionLabelsUsing(fn(array $values): array => self::labels($values))
            ->getOptionLabelUsing(fn(mixed $value): ?string => self::labels([$value])[Typed::string($value)] ?? null);
    }

    /**
     * Who follows the task from now on is exactly who the field holds. People who
     * already followed stay without being checked again; anybody new has to be a
     * user the host lets be added.
     *
     * @param  array<mixed>  $ids
     */
    public static function persist(FinisterreTask $record, array $ids): void
    {
        $wanted = TaskFollowers::normalize($ids);
        $current = TaskFollowers::ids($record);

        $record->unfollow(array_diff($current, $wanted));
        $record->follow(TaskFollowers::onlyCandidates(array_values(array_diff($wanted, $current))));
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, string>
     */
    protected static function labels(array $values): array
    {
        return TaskFollowers::usersById(TaskFollowers::normalize($values))
            ->mapWithKeys(fn(Model $user): array => [Typed::string($user->getKey()) => UserModel::displayName($user)])
            ->all();
    }
}
