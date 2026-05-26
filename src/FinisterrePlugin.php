<?php

namespace Buzkall\Finisterre;

use Buzkall\Finisterre\Filament\Pages\TasksKanbanBoard;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
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
        if (! config('finisterre.active', false)) {
            return;
        }

        $panel
            ->resources([
                FinisterreTaskResource::class
            ])
            ->pages([TasksKanbanBoard::class]);
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
