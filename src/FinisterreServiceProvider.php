<?php

namespace Buzkall\Finisterre;

use Buzkall\Finisterre\Commands\DispatchScheduledCommentsCommand;
use Buzkall\Finisterre\Commands\ResetSequencesCommand;
use Buzkall\Finisterre\Filament\Livewire\FilterTasks;
use Buzkall\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Buzkall\Finisterre\Policies\FinisterreTaskCommentPolicy;
use Buzkall\Finisterre\Policies\FinisterreTaskPolicy;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
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
                    'change_order_column_type_in_finisterre_tasks',
                    'add_scheduling_to_finisterre_task_comments',
                    'convert_order_column_to_integer_in_finisterre_tasks',
                    'add_subject_to_finisterre_tasks',
                ])
                ->hasCommands([
                    DispatchScheduledCommentsCommand::class,
                    ResetSequencesCommand::class,
                ]);
        }
    }

    public function packageBooted(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('finisterre-comments', FinisterreCommentsComponent::class);
            Livewire::component('filter-tasks', FilterTasks::class);
        }

        if (config('finisterre.active', false)) {
            $this->callAfterResolving(Schedule::class, function(Schedule $schedule) {
                $schedule->command('finisterre:dispatch-scheduled-comments')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }

        Gate::policy(FinisterreTask::class, config('finisterre.model_policy', FinisterreTaskPolicy::class));
        Gate::policy(FinisterreTaskComment::class, config('finisterre.comments.model_policy', FinisterreTaskCommentPolicy::class));

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang');

        // this will get copied to the project's public folder when
        // running php artisan filament:assets
        if (class_exists(FilamentAsset::class) && class_exists(Css::class)) {
            FilamentAsset::register(
                [Css::make('finisterre-styles', __DIR__ . '/../resources/dist/finisterre.css')],
                package: 'buzkall/finisterre'
            );
        }

        // remember to run php artisan filament:assets after changing assets in the site
        // also run npm run purge to clean filament styles
    }
}
