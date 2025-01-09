<?php

namespace Buzkall\Finisterre;

use Buzkall\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Buzkall\Finisterre\Policies\FinisterreTaskCommentPolicy;
use Buzkall\Finisterre\Policies\FinisterreTaskPolicy;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FinisterreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // More info: https://github.com/spatie/laravel-package-tools
        $package
            ->name('finisterre')
            ->hasConfigFile()
            ->hasViews()
            ->hasAssets()
            ->hasTranslations()
            ->hasMigration('create_finisterre_tables');
    }

    public function packageBooted(): void
    {
        Livewire::component('finisterre-comments', FinisterreCommentsComponent::class);

        Gate::policy(FinisterreTask::class, config('finisterre.model_policy', FinisterreTaskPolicy::class));
        Gate::policy(FinisterreTaskComment::class, config('finisterre.comments.model_policy', FinisterreTaskCommentPolicy::class));

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
