<?php

namespace Buzkall\Finisterre;

use Buzkall\Finisterre\Filament\Pages\TasksKanbanBoard;
use Buzkall\Finisterre\Filament\Resources\FinisterreTaskResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class Finisterre implements Plugin
{
    public function getId(): string
    {
        return 'finisterre';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                FinisterreTaskResource::class,
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
