<?php

namespace Arzcode\Finisterre;

use Arzcode\Finisterre\Filament\Pages\ManageFinisterreSettings;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Support\SettingsConfig;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class FinisterrePlugin implements Plugin
{
    protected bool|Closure $canViewAllTasks = true;
    protected bool|Closure $canViewOnlyTheirTasks = false;
    protected bool|Closure $canScheduleComments = true;
    protected bool|Closure $canConfigureFinisterre = true;
    protected ?Closure $modifyFollowersQueryUsing = null;

    public function getId(): string
    {
        return 'finisterre';
    }

    public function userCanViewAllTasks(Closure $condition): static
    {
        $this->canViewAllTasks = $condition;

        return $this;
    }

    public function canViewAllTasks(): bool
    {
        return $this->evaluate($this->canViewAllTasks);
    }

    public function userCanViewOnlyTheirTasks(Closure $condition): static
    {
        $this->canViewOnlyTheirTasks = $condition;

        return $this;
    }

    public function canViewOnlyTheirTasks(): bool
    {
        return $this->evaluate($this->canViewOnlyTheirTasks);
    }

    public function userCanScheduleComments(bool|Closure $condition): static
    {
        $this->canScheduleComments = $condition;

        return $this;
    }

    public function canScheduleComments(): bool
    {
        return $this->evaluate($this->canScheduleComments);
    }

    public function userCanConfigureFinisterre(bool|Closure $condition): static
    {
        $this->canConfigureFinisterre = $condition;

        return $this;
    }

    public function canConfigure(): bool
    {
        return $this->evaluate($this->canConfigureFinisterre);
    }

    /**
     * Narrow who can be added as a follower of a task. Without it, every active
     * user can: `fn(Builder $query) => $query->where('is_staff', true)`.
     */
    public function modifyFollowersQueryUsing(?Closure $callback): static
    {
        $this->modifyFollowersQueryUsing = $callback;

        return $this;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyFollowersQuery(Builder $query): void
    {
        if ($this->modifyFollowersQueryUsing instanceof Closure) {
            app()->call($this->modifyFollowersQueryUsing, ['query' => $query]);
        }
    }

    protected function evaluate(bool|Closure $value): bool
    {
        if ($value instanceof Closure) {
            return (bool)app()->call($value);
        }

        return $value;
    }

    public function getAuthUser(): ?Authenticatable
    {
        return Filament::auth()->user();
    }

    public function register(Panel $panel): void
    {
        // Let the database settings override the config-file values
        // (idempotent, also called in the service provider).
        SettingsConfig::apply();

        // Routes are always registered so they never 404/500 when referenced.
        // The `active` flag instead gates access and navigation (see each
        // page/resource canAccess()), read at request time once config is
        // hydrated — registering on a DB read here would be fragile.
        $panel
            ->resources([
                FinisterreTaskResource::class,
            ])
            ->pages([
                TasksKanbanBoard::class,
                ManageFinisterreSettings::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        $plugin = app(static::class);

        if (! $plugin instanceof static) {
            throw new LogicException('The container did not resolve the Finisterre plugin.');
        }

        return $plugin;
    }

    public static function get(): static
    {
        $plugin = filament(static::make()->getId());

        if (! $plugin instanceof static) {
            throw new LogicException('The registered Finisterre plugin is not a ' . static::class . '.');
        }

        return $plugin;
    }
}
