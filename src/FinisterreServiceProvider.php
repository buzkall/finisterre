<?php

namespace Buzkall\Finisterre;

use Buzkall\Finisterre\Commands\FinisterreCommand;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FinisterreServiceProvider extends PackageServiceProvider
{
    public function register(): void
    {
        // Manually load the configuration file, because the package hasn't been registered yet
        $this->mergeConfigFrom(__DIR__ . '/../config/finisterre.php', 'finisterre');

        if (! config('finisterre.active')) {
            return;
        }

        parent::register();
    }

    public function boot(): void
    {
        if (! config('finisterre.active')) {
            return;
        }

        parent::boot();
    }

    public function configurePackage(Package $package): void
    {
        // More info: https://github.com/spatie/laravel-package-tools
        $package
            ->name('finisterre')
            ->hasConfigFile()
            ->hasViews()
            ->hasAssets()
            ->hasTranslations()
            //->hasCommand(FinisterreCommand::class)
            ->hasMigration('create_finisterre_tables');
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            $this->getAssets(),
            package: 'buzkall/finisterre'
        );
    }

    protected function getAssets(): array
    {
        // this will get copied to the project's public folder when
        // running php artisan filament:assets
        return [
            Css::make('finisterre-styles', __DIR__ . '/../resources/dist/finisterre.css'),
        ];
    }
}
