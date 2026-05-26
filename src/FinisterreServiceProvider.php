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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Symfony\Component\Process\Process;

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
            ])
            ->hasInstallCommand(fn(InstallCommand $command) => $command
                ->startWith(fn(InstallCommand $cmd) => $cmd->info('Installing Finisterre…'))
                ->publishMigrations()
                ->endWith(function(InstallCommand $cmd): void {
                    $steps = [
                        fn() => $this->publishConfigFile($cmd),
                        fn() => $this->runMigrations($cmd),
                        fn() => $this->activateInEnvFile($cmd),
                        fn() => $this->publishFilamentAssets($cmd),
                        fn() => $this->patchPanelProviders($cmd),
                        fn() => $this->patchUserModel($cmd),
                        fn() => $this->patchFilamentThemes($cmd),
                        fn() => $this->runNpmBuild($cmd),
                        fn() => $this->printFinalSteps($cmd),
                    ];

                    foreach ($steps as $step) {
                        $cmd->newLine();
                        $step();
                    }
                }));
    }

    protected function publishConfigFile(InstallCommand $command): void
    {
        if (! $command->confirm('Would you like to publish the config file?', true)) {
            return;
        }

        $command->comment('Publishing config…');
        $command->callSilently('vendor:publish', ['--tag' => 'finisterre-config']);
        $command->info('Config file published.');
    }

    protected function runMigrations(InstallCommand $command): void
    {
        if (! $command->confirm('Would you like to run the migrations now?', true)) {
            return;
        }

        $command->comment('Running migrations…');
        $command->call('migrate');
    }

    protected function activateInEnvFile(InstallCommand $command): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $command->warn('No .env file found — add FINISTERRE_ACTIVE=true manually.');

            return;
        }

        $contents = (string)file_get_contents($envPath);

        if (preg_match('/^FINISTERRE_ACTIVE=/m', $contents)) {
            $command->line('FINISTERRE_ACTIVE already present in .env — leaving as-is.');

            return;
        }

        $append = (str_ends_with($contents, "\n") ? '' : "\n") . "FINISTERRE_ACTIVE=true\n";
        file_put_contents($envPath, $contents . $append);
        $command->info('Added FINISTERRE_ACTIVE=true to .env');
    }

    protected function publishFilamentAssets(InstallCommand $command): void
    {
        if (! class_exists(FilamentAsset::class)) {
            return;
        }

        $command->info('Publishing Filament assets…');
        Artisan::call('filament:assets', [], $command->getOutput());
    }

    protected function patchPanelProviders(InstallCommand $command): void
    {
        $dir = app_path('Providers/Filament');

        if (! is_dir($dir)) {
            $command->warn('No app/Providers/Filament directory — register FinisterrePlugin manually in your panel provider.');

            return;
        }

        $files = glob($dir . '/*PanelProvider.php') ?: [];

        if ($files === []) {
            $command->warn('No *PanelProvider.php found — register FinisterrePlugin manually in your panel provider.');

            return;
        }

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = $this->relativePath($file);

            if (str_contains($contents, 'FinisterrePlugin')) {
                $command->line(sprintf('FinisterrePlugin already present in %s — leaving as-is.', $relative));

                continue;
            }

            $patched = $this->addUseImport($contents, 'Buzkall\Finisterre\FinisterrePlugin');
            $patched = $this->injectIntoPluginsArray($patched, 'FinisterrePlugin::make(),');

            if ($patched === null) {
                $command->warn(sprintf('Could not find ->plugins([…]) in %s — add FinisterrePlugin::make() manually.', $relative));

                continue;
            }

            file_put_contents($file, $patched);
            $command->info(sprintf('Patched %s to register FinisterrePlugin.', $relative));
        }
    }

    protected function patchUserModel(InstallCommand $command): void
    {
        $path = app_path('Models/User.php');
        $relative = $this->relativePath($path);

        if (! file_exists($path)) {
            $command->warn(sprintf('%s not found — add FinisterreUserTrait to your User model manually.', $relative));

            return;
        }

        $contents = (string)file_get_contents($path);

        if (str_contains($contents, 'FinisterreUserTrait')) {
            $command->line(sprintf('FinisterreUserTrait already present in %s — leaving as-is.', $relative));

            return;
        }

        $patched = $this->addUseImport($contents, 'Buzkall\Finisterre\Traits\FinisterreUserTrait');
        $patched = $this->addTraitInsideClass($patched, 'FinisterreUserTrait');

        if ($patched === null) {
            $command->warn(sprintf('Could not patch %s — add `use FinisterreUserTrait;` manually.', $relative));

            return;
        }

        file_put_contents($path, $patched);
        $command->info(sprintf('Patched %s to use FinisterreUserTrait.', $relative));
    }

    protected function patchFilamentThemes(InstallCommand $command): void
    {
        $files = glob(resource_path('css/filament/*/theme.css')) ?: [];

        if ($files === []) {
            $command->warn('No theme.css under resources/css/filament/*/theme.css — add the Finisterre @source line manually.');

            return;
        }

        $sourceLine = "@source '../../../../vendor/buzkall/finisterre/resources/views/**/*.blade.php';";

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = $this->relativePath($file);

            if (str_contains($contents, 'buzkall/finisterre/resources/views')) {
                $command->line(sprintf('@source already present in %s — leaving as-is.', $relative));

                continue;
            }

            $contents = rtrim($contents, "\n") . "\n" . $sourceLine . "\n";
            file_put_contents($file, $contents);
            $command->info(sprintf('Patched %s with @source for Finisterre views.', $relative));
        }
    }

    protected function runNpmBuild(InstallCommand $command): void
    {
        if (! $command->confirm('Would you like to run `npm run build` now?', true)) {
            $command->line('Skipped — run `npm run build` manually to compile the Filament theme.');

            return;
        }

        $command->comment('Running npm run build…');

        $process = Process::fromShellCommandline('npm run build', base_path());
        $process->setTimeout(null);
        $process->run(function(string $type, string $buffer) use ($command): void {
            $command->getOutput()->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $command->warn('npm run build failed — see the output above.');
        }
    }

    protected function printFinalSteps(InstallCommand $command): void
    {
        $command->info('Finisterre install complete. Reload your Filament panel.');
    }

    protected function addUseImport(string $contents, string $fqcn): string
    {
        $pattern = '/^use\s+' . preg_quote($fqcn, '/') . ';/m';
        if (preg_match($pattern, $contents)) {
            return $contents;
        }

        if (preg_match_all('/^use\s+[^;]+;\n/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            $insertAt = $last[1] + strlen($last[0]);

            return substr($contents, 0, $insertAt) . 'use ' . $fqcn . ";\n" . substr($contents, $insertAt);
        }

        if (preg_match('/^namespace\s+[^;]+;\n/m', $contents, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = $m[0][1] + strlen($m[0][0]);

            return substr($contents, 0, $insertAt) . "\nuse " . $fqcn . ";\n" . substr($contents, $insertAt);
        }

        return $contents . "\nuse " . $fqcn . ";\n";
    }

    protected function injectIntoPluginsArray(string $contents, string $entry): ?string
    {
        $open = strpos($contents, '->plugins([');
        if ($open === false) {
            return null;
        }

        $closeAt = $this->findMatchingArrayClose($contents, $open + strlen('->plugins(['));
        if ($closeAt === null) {
            return null;
        }

        $lineStart = strrpos(substr($contents, 0, $closeAt), "\n");
        $closeIndent = $lineStart === false ? '' : substr($contents, $lineStart + 1, $closeAt - $lineStart - 1);
        $closeIndent = preg_replace('/[^\s].*$/', '', $closeIndent);
        $itemIndent = $closeIndent . '    ';

        $before = rtrim(substr($contents, 0, $closeAt));

        if (! str_ends_with($before, ',') && ! str_ends_with($before, '[')) {
            $before .= ',';
        }

        $insertion = "\n{$itemIndent}{$entry}\n{$closeIndent}";

        return $before . $insertion . substr($contents, $closeAt);
    }

    protected function findMatchingArrayClose(string $contents, int $startAfterOpenBracket): ?int
    {
        $len = strlen($contents);
        $arrayDepth = 1;
        $parenDepth = 0;
        $stringDelim = null;

        for ($pos = $startAfterOpenBracket; $pos < $len; $pos++) {
            $c = $contents[$pos];

            if ($stringDelim !== null) {
                if ($c === '\\') {
                    $pos++;

                    continue;
                }
                if ($c === $stringDelim) {
                    $stringDelim = null;
                }

                continue;
            }

            if ($c === "'" || $c === '"') {
                $stringDelim = $c;

                continue;
            }

            if ($c === '/' && $pos + 1 < $len && $contents[$pos + 1] === '/') {
                $nl = strpos($contents, "\n", $pos);
                $pos = $nl === false ? $len : $nl;

                continue;
            }

            if ($c === '[') {
                $arrayDepth++;
            } elseif ($c === ']') {
                $arrayDepth--;
                if ($arrayDepth === 0) {
                    return $pos;
                }
            } elseif ($c === '(') {
                $parenDepth++;
            } elseif ($c === ')') {
                $parenDepth--;
            }
        }

        return null;
    }

    protected function addTraitInsideClass(string $contents, string $traitShortName): ?string
    {
        if (! preg_match('/(class\s+\w+[^{]*\{)/', $contents, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $insertAt = $m[1][1] + strlen($m[1][0]);

        return substr($contents, 0, $insertAt) . "\n    use {$traitShortName};" . substr($contents, $insertAt);
    }

    protected function relativePath(string $absolutePath): string
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($absolutePath, $base) ? substr($absolutePath, strlen($base)) : $absolutePath;
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
