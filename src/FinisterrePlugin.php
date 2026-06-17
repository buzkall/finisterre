<?php

namespace Buzkall\Finisterre;

use Buzkall\Finisterre\Filament\Pages\ManageFinisterreSettings;
use Buzkall\Finisterre\Filament\Pages\TasksKanbanBoard;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Buzkall\Finisterre\Support\SettingsConfig;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;

class FinisterrePlugin implements Plugin
{
    protected bool|Closure $canViewAllTasks = true;
    protected bool|Closure $canViewOnlyTheirTasks = false;
    protected bool|Closure $canScheduleComments = true;
    protected bool|Closure $canConfigureFinisterre = true;

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
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
