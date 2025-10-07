<?php

namespace Buzkall\Finisterre;

use Buzkall\Finisterre\Filament\Livewire\FilterTasks;
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
            ->hasConfigFile();

        if (config('finisterre.active', false)) {
            $package
                ->hasViews()
                ->hasAssets()
                ->hasTranslations()
                ->hasMigrations([
                    'create_finisterre_tables',
                    'add_subtasks_to_finisterre_tasks',
                    'add_archived_to_finisterre_tasks',
                    'add_task_changes_table',
                ]);
        }
    }

    public function packageBooted(): void
    {
        if (class_exists(\Livewire\Livewire::class)) {
            Livewire::component('finisterre-comments', FinisterreCommentsComponent::class);
            Livewire::component('filter-tasks', FilterTasks::class);
        }

        Gate::policy(FinisterreTask::class, config('finisterre.model_policy', FinisterreTaskPolicy::class));
        Gate::policy(FinisterreTaskComment::class, config('finisterre.comments.model_policy', FinisterreTaskCommentPolicy::class));

        // this will get copied to the project's public folder when
        // running php artisan filament:assets
        if (class_exists(\Filament\Support\Facades\FilamentAsset::class) && class_exists(\Filament\Support\Assets\Css::class)) {
            FilamentAsset::register(
                [Css::make('finisterre-styles', __DIR__ . '/../resources/dist/finisterre.css')],
                package: 'buzkall/finisterre'
            );
        }

        // remember to run php artisan filament:assets after changing assets in the site
        // also run npm run purge to clean filament styles
    }
}
