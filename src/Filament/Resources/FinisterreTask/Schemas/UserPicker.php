<?php

namespace Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas;

use Arzcode\Finisterre\Support\TaskFollowers;
use Arzcode\Finisterre\Support\UserModel;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * The field that picks people (the assignee, the followers, the recipients of a
 * comment, the fallback user). A panel usually has a handful of users: with fewer
 * than TOGGLE_BUTTONS_BELOW to pick from they are all shown as buttons, and with
 * more a select lists them all and filters them as you type.
 */
class UserPicker
{
    public const int TOGGLE_BUTTONS_BELOW = 6;

    /**
     * @param  Closure(): Builder<Model>  $candidates  The people who can be picked
     */
    public static function make(string $name, Closure $candidates, bool $multiple = false, string|BackedEnum|null $icon = null): ToggleButtons|Select
    {
        // Whoever the field holds keeps their name in it, even when the host no
        // longer offers them: the options are checked when the form is saved.
        $options = fn(ToggleButtons|Select $component): array => self::options($candidates(), $component->getState());

        if ($candidates()->count() < self::TOGGLE_BUTTONS_BELOW) {
            return ToggleButtons::make($name)
                ->options($options)
                ->multiple($multiple)
                ->inline();
        }

        return Select::make($name)
            ->options($options)
            ->multiple($multiple)
            ->searchable()
            ->prefixIcon($icon)
            ->placeholder(__('finisterre::finisterre.followers.add'));
    }

    /**
     * @param  Builder<Model>  $candidates
     * @return array<string, string>
     */
    protected static function options(Builder $candidates, mixed $state): array
    {
        $options = UserModel::options($candidates);
        $missing = array_values(array_diff(TaskFollowers::normalize(Arr::wrap($state)), array_map(intval(...), array_keys($options))));

        if ($missing === []) {
            return $options;
        }

        return $options + UserModel::toOptions(UserModel::class()::query()->findMany($missing));
    }
}
