<?php

namespace Arzcode\Finisterre\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
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
        $id = auth()->id();

        return $id === null ? null : (int)$id;
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
