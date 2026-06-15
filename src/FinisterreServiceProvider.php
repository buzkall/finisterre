<?php

namespace Buzkall\Finisterre;

use Buzkall\Finisterre\Commands\DispatchScheduledCommentsCommand;
use Buzkall\Finisterre\Commands\ResetSequencesCommand;
use Buzkall\Finisterre\Commands\UninstallCommand;
use Buzkall\Finisterre\Filament\Livewire\FilterTasks;
use Buzkall\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Buzkall\Finisterre\Policies\FinisterreTaskCommentPolicy;
use Buzkall\Finisterre\Policies\FinisterreTaskPolicy;
use Buzkall\Finisterre\Settings\FinisterreSettings;
use Buzkall\Finisterre\Support\SettingsConfig;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Symfony\Component\Process\Process;

class FinisterreServiceProvider extends PackageServiceProvider
{
    /** Canonical board slug suggested by the installer. */
    protected const DEFAULT_BOARD_SLUG = 'tasks';

    /** Fallback board slug when the canonical one is already taken. */
    protected const FALLBACK_BOARD_SLUG = 'finisterre';

    public function configurePackage(Package $package): void
    {
        // More info: https://github.com/spatie/laravel-package-tools
        $package
            ->name('finisterre')
            ->hasConfigFile()
            ->hasViews()
            ->hasAssets()
            ->hasTranslations()
            ->hasMigrations(self::migrationNames())
            ->hasCommands([
                DispatchScheduledCommentsCommand::class,
                ResetSequencesCommand::class,
                UninstallCommand::class,
            ])
            ->hasInstallCommand(fn(InstallCommand $command) => $command
                ->startWith(fn(InstallCommand $cmd) => $cmd->info('Installing Finisterre…'))
                ->publishMigrations()
                ->endWith(function(InstallCommand $cmd): void {
                    $steps = [
                        fn() => $this->publishConfigFile($cmd),
                        fn() => $this->publishSettingsMigration($cmd),
                        fn() => $this->runMigrations($cmd),
                        fn() => $this->activateViaSettings($cmd),
                        fn() => $this->configureBoardSlug($cmd),
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

    /**
     * Base names (no timestamp prefix / extension) of the package's published
     * migrations. Single source of truth for `hasMigrations()` and the
     * uninstaller's cleanup. Excludes the settings migration, which is
     * published separately and named with its own timestamp.
     *
     * @return list<string>
     */
    public static function migrationNames(): array
    {
        return [
            'create_finisterre_tables',
            'add_subtasks_to_finisterre_tasks',
            'add_archived_to_finisterre_tasks',
            'add_task_changes_table',
            'change_order_column_type_in_finisterre_tasks',
            'add_scheduling_to_finisterre_task_comments',
            'convert_order_column_to_integer_in_finisterre_tasks',
            'add_subject_to_finisterre_tasks',
        ];
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

    protected function publishSettingsMigration(InstallCommand $command): void
    {
        // The host may already use spatie/laravel-settings (its own settings
        // table). Only publish the base migration when the table is missing,
        // otherwise `migrate` would try to recreate an existing table.
        if (Schema::hasTable('settings')) {
            $command->line('A settings table already exists — skipping the settings migration.');

            return;
        }

        $command->comment('Publishing the settings table migration…');
        $command->callSilently('vendor:publish', [
            '--provider' => 'Spatie\LaravelSettings\LaravelSettingsServiceProvider',
            '--tag'      => 'migrations',
        ]);
        $command->info('Settings table migration published.');
    }

    protected function activateViaSettings(InstallCommand $command): void
    {
        try {
            // The settings migration seeds these rows, but only the first time it
            // runs. If they're missing (e.g. removed by a prior uninstall, which
            // leaves the migration record behind), a plain `migrate` won't bring
            // them back — so seed any gaps directly. Idempotent: nothing to do on
            // a clean install where the migration already seeded everything.
            $created = SettingsConfig::seedMissing();

            $command->info($created > 0
                ? sprintf('Finisterre settings seeded (%d created) — active in all environments by default. Manage it from the settings page.', $created)
                : 'Finisterre installed — active in all environments by default. Manage it from the settings page.');
        } catch (\Throwable) {
            $command->warn('Could not seed Finisterre settings automatically — run the migrations, then configure it from the settings page.');
        }
    }

    protected function configureBoardSlug(InstallCommand $command): void
    {
        try {
            $settings = app(FinisterreSettings::class);
            $stored = $settings->slug;
            $panelSlug = config('finisterre.panel_slug', 'admin');

            // Probe the registered routes for a free board path, so the prompt never
            // suggests a colliding default. We can't read config('finisterre.slug')
            // here: SettingsConfig::apply() has already overridden it at boot with the
            // stored value, which would resurface a stale slug from a prior installation.
            // pathHasRoute() ignores Finisterre's own routes, so on a re-install the
            // board path it registered last time isn't treated as a collision.
            $suggested = $this->firstFreeBoardSlug($panelSlug);
            $default = $panelSlug . '/' . $suggested;

            if ($suggested !== self::DEFAULT_BOARD_SLUG) {
                $command->warn(sprintf(
                    '/%s/%s is already taken by another route in this panel — suggesting /%s/%s instead.',
                    $panelSlug,
                    self::DEFAULT_BOARD_SLUG,
                    $panelSlug,
                    $suggested
                ));
            }

            $command->line(sprintf('The task board will live at /%s/%s.', $panelSlug, $suggested));

            // Re-prompt while the chosen path collides with an existing route.
            do {
                $answer = (string)$command->ask('URL path for the Finisterre task board', $default);
                // Only the board segment is configurable; the panel slug is fixed by config.
                $slug = (string)str($answer)->trim()->trim('/')->afterLast('/');

                if ($slug === '') {
                    return;
                }

                if (! $this->pathHasRoute($panelSlug, $slug)) {
                    break;
                }

                $command->warn(sprintf('/%s/%s is already registered by another route in this panel.', $panelSlug, $slug));
            } while (! $command->confirm('Use it anyway?', false));

            if ($slug !== $stored) {
                $settings->slug = $slug;
                $settings->save();
                $command->info(sprintf("Board slug set to '%s'.", $slug));
            }
        } catch (\Throwable) {
            $command->warn('Could not set the board slug — change it later from the settings page.');
        }
    }

    /**
     * The first free board path: the canonical `tasks`, then `finisterre`, then
     * numbered `finisterre-2`, `finisterre-3`, … — skipping any path a non-Finisterre
     * route already serves.
     */
    protected function firstFreeBoardSlug(string $panelSlug): string
    {
        foreach ([self::DEFAULT_BOARD_SLUG, self::FALLBACK_BOARD_SLUG] as $candidate) {
            if (! $this->pathHasRoute($panelSlug, $candidate)) {
                return $candidate;
            }
        }

        $suffix = 2;

        while ($this->pathHasRoute($panelSlug, self::FALLBACK_BOARD_SLUG . '-' . $suffix)) {
            $suffix++;
        }

        return self::FALLBACK_BOARD_SLUG . '-' . $suffix;
    }

    /**
     * Whether a route NOT belonging to Finisterre already serves /{panelSlug}/{slug}
     * (the board path itself or any of its sub-paths). Finisterre's own routes are
     * skipped so a re-install doesn't flag the board it registered last time.
     */
    protected function pathHasRoute(string $panelSlug, string $slug): bool
    {
        $target = trim($panelSlug . '/' . $slug, '/');

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (str_starts_with(ltrim($route->getActionName(), '\\'), 'Buzkall\\Finisterre\\')) {
                continue;
            }

            $uri = trim($route->uri(), '/');

            if ($uri === $target || str_starts_with($uri, $target . '/')) {
                return true;
            }
        }

        return false;
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

            $withImport = $this->addUseImport($contents, 'Buzkall\Finisterre\FinisterrePlugin');

            // Inject into an existing ->plugins([…]) call, or add a new one to
            // the $panel chain when the provider doesn't have one yet.
            $patched = $this->injectIntoPluginsArray($withImport, 'FinisterrePlugin::make(),')
                ?? $this->addPluginsArray($withImport);

            if ($patched === null) {
                $command->warn(sprintf('Could not patch %s — add ->plugins([FinisterrePlugin::make()]) manually.', $relative));

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

        // Both finisterre's own views and the flowforge Kanban board views need
        // their Tailwind utilities compiled into the host theme, otherwise the
        // task board renders unstyled.
        $sourceLines = [
            'buzkall/finisterre/resources/views'  => "@source '../../../../vendor/buzkall/finisterre/resources/views/**/*.blade.php';",
            'relaticle/flowforge/resources/views' => "@source '../../../../vendor/relaticle/flowforge/resources/views/**/*.blade.php';",
        ];

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = $this->relativePath($file);

            $added = [];
            foreach ($sourceLines as $marker => $line) {
                if (str_contains($contents, $marker)) {
                    continue;
                }

                $contents = rtrim($contents, "\n") . "\n" . $line . "\n";
                $added[] = $marker;
            }

            if ($added === []) {
                $command->line(sprintf('@source lines already present in %s — leaving as-is.', $relative));

                continue;
            }

            file_put_contents($file, $contents);
            $command->info(sprintf('Patched %s with @source for Finisterre and Flowforge views.', $relative));
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
        // Match ->plugins( … [ tolerating whitespace/newlines before the array,
        // so both `->plugins([` and `->plugins(\n    [` are detected.
        if (! preg_match('/->plugins\(\s*\[/', $contents, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $bracketAt = $m[0][1] + strlen($m[0][0]) - 1;

        $closeAt = $this->findMatchingArrayClose($contents, $bracketAt + 1);
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

    protected function addPluginsArray(string $contents): ?string
    {
        // Add a ->plugins([…]) call at the bottom of the panel configuration
        // chain, just before the terminating `;` of `return $panel->…;`.
        if (! preg_match('/return\s+\$panel\b/', $contents, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $semicolonAt = $this->findStatementEnd($contents, $m[0][1] + strlen($m[0][0]));
        if ($semicolonAt === null) {
            return null;
        }

        $insertion = "\n            ->plugins([\n                FinisterrePlugin::make(),\n            ])";

        return substr($contents, 0, $semicolonAt) . $insertion . substr($contents, $semicolonAt);
    }

    protected function findStatementEnd(string $contents, int $start): ?int
    {
        $len = strlen($contents);
        $paren = 0;
        $bracket = 0;
        $brace = 0;
        $stringDelim = null;

        for ($pos = $start; $pos < $len; $pos++) {
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

            if ($c === '(') {
                $paren++;
            } elseif ($c === ')') {
                $paren--;
            } elseif ($c === '[') {
                $bracket++;
            } elseif ($c === ']') {
                $bracket--;
            } elseif ($c === '{') {
                $brace++;
            } elseif ($c === '}') {
                $brace--;
            } elseif ($c === ';' && $paren === 0 && $bracket === 0 && $brace === 0) {
                return $pos;
            }
        }

        return null;
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
        // Make the package's settings migration discoverable by `migrate` and
        // register the settings class so spatie/laravel-settings can cache it.
        $this->loadMigrationsFrom(__DIR__ . '/../database/settings');
        config()->push('settings.settings', FinisterreSettings::class);

        // Let the database settings override the config-file defaults.
        SettingsConfig::apply();

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
                [
                    Css::make('finisterre-styles', __DIR__ . '/../resources/css/app.css'),
                    // Works around a Filament v5 RichEditor bug where pasting multiple
                    // images at once drops all but the last one.
                    Js::make('finisterre-rich-editor-paste-fix', __DIR__ . '/../resources/dist/finisterre-rich-editor-paste-fix.js'),
                ],
                package: 'buzkall/finisterre'
            );
        }

        // remember to run php artisan filament:assets after changing assets in the site
    }
}
