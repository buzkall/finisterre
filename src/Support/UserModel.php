<?php

namespace Arzcode\Finisterre\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class UserModel
{
    /**
     * The host's user model, as configured in finisterre.authenticatable.
     *
     * @return class-string<Model>
     */
    public static function class(): string
    {
        $class = config('finisterre.authenticatable');

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new InvalidArgumentException('finisterre.authenticatable must be an Eloquent model class.');
        }

        return $class;
    }

    /**
     * Users a task can be assigned to, narrowed by FinisterreUserTrait::scopeAssignableUsers().
     *
     * @return Builder<Model>
     */
    public static function assignableQuery(): Builder
    {
        return self::scopeAssignable(self::class()::query());
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function scopeAssignable(Builder $query): Builder
    {
        $model = $query->getModel();

        // The scope narrows the builder in place.
        if (method_exists($model, 'scopeAssignableUsers')) {
            $model->scopeAssignableUsers($query);
        }

        return $query;
    }

    /**
     * The users in a query as options, keyed by id and sorted by name.
     *
     * @param  Builder<Model>  $query
     * @return array<string, string>
     */
    public static function options(Builder $query): array
    {
        // Qualified: a host's scope may join a table that has a column of the same name.
        return $query
            ->orderBy($query->qualifyColumn(self::nameColumns()[0]))
            ->get()
            ->pipe(self::toOptions(...));
    }

    /**
     * Users as select options: their key and their label.
     *
     * @param  iterable<int|string, Model>  $users
     * @return array<string, string>
     */
    public static function toOptions(iterable $users): array
    {
        return collect($users)
            ->mapWithKeys(fn(Model $user): array => [Typed::string($user->getKey()) => self::label($user)])
            ->all();
    }

    /**
     * The display name, or the first name column for a model without FinisterreUserTrait.
     */
    public static function label(Model $user): string
    {
        return self::displayName($user) ?: Typed::string($user->getAttribute(self::nameColumns()[0]));
    }

    /**
     * @return non-empty-list<string>
     */
    protected static function nameColumns(): array
    {
        return Typed::strings(Arr::wrap(config('finisterre.authenticatable_attribute', 'name') ?? 'name')) ?: ['name'];
    }

    /**
     * The select expression FinisterreUserTrait builds for the user's display name.
     */
    public static function nameSelectExpression(): Expression|string
    {
        $class = self::class();
        $expression = method_exists($class, 'getUserNameSelectExpression') ? $class::getUserNameSelectExpression() : null;

        return $expression instanceof Expression || is_string($expression) ? $expression : 'name';
    }

    /**
     * The signed-in user's id. User foreign keys are unsigned big integers, so a
     * guard handing out string identifiers still maps onto them.
     */
    public static function authId(): ?int
    {
        return Typed::nullableInt(TaskAccess::user()?->getAuthIdentifier());
    }

    /**
     * The name FinisterreUserTrait::getUserDisplayName() builds for the user.
     */
    public static function displayName(Model $user): string
    {
        $name = method_exists($user, 'getUserDisplayName') ? $user->getUserDisplayName() : null;

        return is_string($name) ? $name : '';
    }

    /**
     * Same as the Notifiable trait's notify(), for a user typed as a plain model.
     */
    public static function notify(Model $user, Notification $notification): void
    {
        app(Dispatcher::class)->send($user, $notification);
    }
}
