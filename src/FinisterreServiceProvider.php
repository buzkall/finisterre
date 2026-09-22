<?php

namespace Arzcode\Finisterre;

use Arzcode\Finisterre\Commands\DispatchScheduledCommentsCommand;
use Arzcode\Finisterre\Commands\PrivatizeAttachmentsCommand;
use Arzcode\Finisterre\Commands\ResetSequencesCommand;
use Arzcode\Finisterre\Commands\UninstallCommand;
use Arzcode\Finisterre\Commands\UpdateCommand;
use Arzcode\Finisterre\Controllers\FilamentRouteController;
use Arzcode\Finisterre\Filament\Livewire\FilterTasks;
use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\Filament\Livewire\FinisterreSubtasksComponent;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Observers\FinisterreMediaObserver;
use Arzcode\Finisterre\Policies\FinisterreTaskCommentPolicy;
use Arzcode\Finisterre\Policies\FinisterreTaskPolicy;
use Arzcode\Finisterre\Settings\FinisterreSettings;
use Arzcode\Finisterre\Support\AttachmentsDisk;
use Arzcode\Finisterre\Support\DependencyMigrations;
use Arzcode\Finisterre\Support\FilamentThemes;
use Arzcode\Finisterre\Support\PackageMigrations;
use Arzcode\Finisterre\Support\SettingsConfig;
use Arzcode\Finisterre\Traits\FinisterreUserTrait;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Process\Process;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class FinisterreServiceProvider extends PackageServiceProvider
{
    /** Canonical board slug suggested by the installer. */
    protected const DEFAULT_BOARD_SLUG = 'tasks';

    /** Fallback board slug when the canonical one is already taken. */
    protected const FALLBACK_BOARD_SLUG = 'finisterre';

    /**
     * Panels the install left without a theme, keyed by id with the path the
     * theme is expected at. Reported again at the end: without a theme the
     * board renders unstyled, and "install complete" would hide that.
     *
     * @var array<string, string>
     */
    protected array $panelsWithoutTheme = [];

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
                PrivatizeAttachmentsCommand::class,
                ResetSequencesCommand::class,
                UninstallCommand::class,
                UpdateCommand::class,
            ])
            ->hasInstallCommand(fn(InstallCommand $command) => $command
                ->startWith(fn(InstallCommand $cmd) => intro('Installing Finisterre'))
                ->publishMigrations()
                ->endWith(function(InstallCommand $cmd): void {
                    $steps = [
                        fn() => $this->publishConfigFile($cmd),
                        fn() => $this->publishSettingsMigration($cmd),
                        fn() => $this->publishDependencyMigrations($cmd),
                        fn() => $this->runMigrations($cmd),
                        fn() => $this->configureAttachmentsDisk($cmd),
                        $this->activateViaSettings(...),
                        $this->configureBoardSlug(...),
                        fn() => $this->publishFilamentAssets($cmd),
                        $this->patchPanelProviders(...),
                        $this->patchUserModel(...),
                        fn() => $this->ensureFilamentThemes($cmd),
                        $this->patchFilamentThemes(...),
                        fn() => $this->runNpmBuild($cmd),
                        $this->printFinalSteps(...),
                    ];

                    foreach ($steps as $step) {
                        $cmd->newLine();
                        $step();
                    }
                }));
    }

    /**
     * Register the package config with a recursive merge so a host app only
     * needs to declare the keys it overrides, including nested ones.
     *
     * Overrides spatie/laravel-package-tools' default merge (Laravel's shallow
     * `mergeConfigFrom()`) so this runs once instead of layering a recursive
     * pass on top of the shallow one. We can't reuse Laravel's
     * `replaceConfigRecursivelyFrom()` either, because it merges list arrays by
     * index — a shorter published list would keep the package's trailing
     * entries. `deepMergeConfig()` replaces lists wholesale instead.
     *
     * Config publishing is handled separately by `bootPackageConfigs()`, so it
     * is unaffected by this override.
     */
    public function registerPackageConfigs(): self
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return $this;
        }

        $config = $this->app['config'];

        $config->set('finisterre', $this->deepMergeConfig(
            require __DIR__ . '/../config/finisterre.php',
            $config->get('finisterre', []),
        ));

        return $this;
    }

    /**
     * Recursively merge published config values over the package defaults.
     *
     * Associative arrays are merged key-by-key so a host app can override a
     * single nested key without redeclaring its siblings. List arrays and
     * scalars are replaced wholesale — otherwise a shorter published list would
     * inherit the package's trailing entries via index-based merging.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function deepMergeConfig(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                is_array($value) && ! array_is_list($value)
                && isset($defaults[$key]) && is_array($defaults[$key]) && ! array_is_list($defaults[$key])
            ) {
                $defaults[$key] = $this->deepMergeConfig($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
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
            'create_finisterre_subtasks_table',
            'add_cover_media_id_to_finisterre_tasks',
            'add_editor_files_to_finisterre_tasks',
        ];
    }

    protected function publishConfigFile(InstallCommand $command): void
    {
        if (! confirm(label: 'Would you like to publish the config file?', default: true)) {
            return;
        }

        $command->callSilently('vendor:publish', ['--tag' => 'finisterre-config']);
        info('Config file published.');
    }

    protected function runMigrations(InstallCommand $command): void
    {
        if (! confirm(label: 'Would you like to run the migrations now?', default: true)) {
            note('Skipped — run `php artisan migrate` when you are ready.');

            return;
        }

        $command->call('migrate');
    }

    /**
     * Offer the private disk while there is nothing to move yet. Run again on a host
     * that already has files on the public disk, it moves them too.
     */
    protected function configureAttachmentsDisk(InstallCommand $command): void
    {
        if (! AttachmentsDisk::isPublic()) {
            note(sprintf("Attachments are already on the private '%s' disk.", AttachmentsDisk::name()));

            return;
        }

        if (! confirm(label: 'Store attachments on a private disk, served only to users who can see their task?', default: true)) {
            note("Skipped — attachments go to the public disk, where anybody with a file's URL can open them. `php artisan finisterre:update` offers the switch again.");

            return;
        }

        $problems = AttachmentsDisk::makePrivate();

        if ($problems !== []) {
            warning("Could not switch to the private disk on its own — finish by hand:\n" . implode("\n", array_map(fn(string $problem): string => '  • ' . $problem, $problems)));

            return;
        }

        info(sprintf("Attachments now go to the private '%s' disk, added to config/filesystems.php.", AttachmentsDisk::PRIVATE_DISK));

        if (array_sum(AttachmentsDisk::publicLeftovers()) > 0) {
            $command->call('finisterre:privatize-attachments', ['--force' => true]);
        }
    }

    protected function publishSettingsMigration(InstallCommand $command): void
    {
        // The host may already use spatie/laravel-settings (its own settings
        // table). Only publish the base migration when the table is missing,
        // otherwise `migrate` would try to recreate an existing table.
        if (Schema::hasTable('settings')) {
            note('A settings table already exists — skipping the settings migration.');

            return;
        }

        $command->callSilently('vendor:publish', [
            '--provider' => LaravelSettingsServiceProvider::class,
            '--tag'      => 'migrations',
        ]);
        info('Settings table migration published.');
    }

    /**
     * Tasks carry tags and attachments, so the tables of spatie/laravel-tags
     * and spatie/laravel-medialibrary have to exist too. Both packages ship
     * their migration as a stub that `migrate` only sees once published, and a
     * host that never used them has no reason to have done so — publish what
     * neither the database nor database/migrations already accounts for. The
     * same goes for Laravel's notifications table, which the panel
     * notifications tasks send are stored in.
     */
    protected function publishDependencyMigrations(InstallCommand $command): void
    {
        $missing = DependencyMigrations::missing();

        if ($missing === []) {
            note('The tags, media and notifications tables Finisterre relies on are in place, or their migrations are already published.');

            return;
        }

        foreach ($missing as $dependency) {
            $command->callSilently('vendor:publish', DependencyMigrations::publishArguments($dependency));

            if (PackageMigrations::publishedFile($dependency['name']) === null) {
                warning(sprintf(
                    'Could not publish the %s migration (%s) — run `%s` by hand before `php artisan migrate`.',
                    $dependency['package'],
                    $dependency['purpose'],
                    DependencyMigrations::publishCommand($dependency)
                ));

                continue;
            }

            info(sprintf(
                '%s migration published — Finisterre stores %s in the %s table(s).',
                $dependency['package'],
                $dependency['purpose'],
                implode(', ', $dependency['tables'])
            ));
        }
    }

    protected function activateViaSettings(): void
    {
        try {
            // The settings migration seeds these rows, but only the first time it
            // runs. If they're missing (e.g. removed by a prior uninstall, which
            // leaves the migration record behind), a plain `migrate` won't bring
            // them back — so seed any gaps directly. Idempotent: nothing to do on
            // a clean install where the migration already seeded everything.
            $created = SettingsConfig::seedMissing();

            info($created > 0
                ? sprintf('Finisterre settings seeded (%d created) — active in all environments by default. Manage it from the settings page.', $created)
                : 'Finisterre installed — active in all environments by default. Manage it from the settings page.');
        } catch (Throwable) {
            warning('Could not seed Finisterre settings automatically — run the migrations, then configure it from the settings page.');
        }
    }

    protected function configureBoardSlug(): void
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
                warning(sprintf(
                    '/%s/%s is already taken by another route in this panel — suggesting /%s/%s instead.',
                    $panelSlug,
                    self::DEFAULT_BOARD_SLUG,
                    $panelSlug,
                    $suggested
                ));
            }

            note(sprintf('The task board will live at /%s/%s.', $panelSlug, $suggested));

            // Re-prompt while the chosen path collides with an existing route.
            do {
                $answer = text(
                    label: 'URL path for the Finisterre task board',
                    default: $default,
                    hint: 'Only the last segment is used — the panel slug is fixed by config.',
                );
                // Only the board segment is configurable; the panel slug is fixed by config.
                $slug = (string)str($answer)->trim()->trim('/')->afterLast('/');

                if ($slug === '') {
                    return;
                }

                if (! $this->pathHasRoute($panelSlug, $slug)) {
                    break;
                }

                warning(sprintf('/%s/%s is already registered by another route in this panel.', $panelSlug, $slug));
            } while (! confirm(label: 'Use it anyway?', default: false));

            if ($slug !== $stored) {
                $settings->slug = $slug;
                $settings->save();
                info(sprintf("Board slug set to '%s'.", $slug));
            }
        } catch (Throwable) {
            warning('Could not set the board slug — change it later from the settings page.');
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
            if (str_starts_with(ltrim($route->getActionName(), '\\'), 'Arzcode\\Finisterre\\')) {
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

        info('Publishing Filament assets…');
        Artisan::call('filament:assets', [], $command->getOutput());
    }

    protected function patchPanelProviders(): void
    {
        $dir = app_path('Providers/Filament');

        if (! is_dir($dir)) {
            warning('No app/Providers/Filament directory — register FinisterrePlugin manually in your panel provider.');

            return;
        }

        $files = glob($dir . '/*PanelProvider.php') ?: [];

        if ($files === []) {
            warning('No *PanelProvider.php found — register FinisterrePlugin manually in your panel provider.');

            return;
        }

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = $this->relativePath($file);

            if (str_contains($contents, 'FinisterrePlugin')) {
                note(sprintf('FinisterrePlugin already present in %s — leaving as-is.', $relative));

                continue;
            }

            $withImport = $this->addUseImport($contents, FinisterrePlugin::class);

            // Inject into an existing ->plugins([…]) call, or add a new one to
            // the $panel chain when the provider doesn't have one yet.
            $patched = $this->injectIntoPluginsArray($withImport, 'FinisterrePlugin::make(),')
                ?? $this->addPluginsArray($withImport);

            if ($patched === null) {
                warning(sprintf('Could not patch %s — add ->plugins([FinisterrePlugin::make()]) manually.', $relative));

                continue;
            }

            file_put_contents($file, $patched);
            info(sprintf('Patched %s to register FinisterrePlugin.', $relative));
        }
    }

    protected function patchUserModel(): void
    {
        $path = app_path('Models/User.php');
        $relative = $this->relativePath($path);

        if (! file_exists($path)) {
            warning(sprintf('%s not found — add FinisterreUserTrait to your User model manually.', $relative));

            return;
        }

        $contents = (string)file_get_contents($path);

        if (str_contains($contents, 'FinisterreUserTrait')) {
            note(sprintf('FinisterreUserTrait already present in %s — leaving as-is.', $relative));

            return;
        }

        $patched = $this->addUseImport($contents, FinisterreUserTrait::class);
        $patched = $this->addTraitInsideClass($patched, 'FinisterreUserTrait');

        if ($patched === null) {
            warning(sprintf('Could not patch %s — add `use FinisterreUserTrait;` manually.', $relative));

            return;
        }

        file_put_contents($path, $patched);
        info(sprintf('Patched %s to use FinisterreUserTrait.', $relative));
    }

    /**
     * Offer to create a theme for every registered panel that has none.
     *
     * Finisterre's views and the flowforge board are compiled by the host's
     * Filament theme, so a panel without one shows the board unstyled no matter
     * how many times `npm run build` runs. Filament's own `make:filament-theme`
     * does the wiring — the theme file, the `vite.config.js` input and the
     * `viteTheme()` call on the panel provider — so it is what gets called. It
     * runs in a separate process: Laravel reconfigures Laravel Prompts' static
     * state on every command run, so calling it in-process with
     * `--no-interaction` would leave the rest of this install answering its
     * own prompts with their defaults. Non-interactive, it also builds the bare
     * theme once; harmless, since the `@source` lines go in right after and the
     * install ends with a build of its own.
     */
    protected function ensureFilamentThemes(InstallCommand $command): void
    {
        $panels = FilamentThemes::panelsWithoutTheme();

        if ($panels === []) {
            return;
        }

        warning('Finisterre\'s views are compiled by your panel\'s Filament theme — without one the task board renders unstyled.');

        foreach ($panels as $panelId => $path) {
            $confirmed = confirm(
                label: sprintf('The %s panel has no theme at %s. Create one now with `php artisan make:filament-theme %s`?', $panelId, $path, $panelId),
                default: true,
            );

            if (! $confirmed) {
                note(sprintf('Skipped — run `php artisan make:filament-theme %s`, then `php artisan finisterre:install` again to add the @source lines.', $panelId));
                $this->panelsWithoutTheme[$panelId] = $path;

                continue;
            }

            note('Filament compiles the bare theme once; the install builds it again after adding the @source lines.');

            $process = new Process([PHP_BINARY, 'artisan', 'make:filament-theme', $panelId, '--no-interaction'], base_path());
            $process->setTimeout(null);
            $process->run(function(string $type, string $buffer) use ($command): void {
                $command->getOutput()->write($buffer);
            });

            if (! $process->isSuccessful() || ! is_file(base_path($path))) {
                warning(sprintf('Could not create the %s theme — run `php artisan make:filament-theme %s` by hand, then `php artisan finisterre:install` again.', $panelId, $panelId));
                $this->panelsWithoutTheme[$panelId] = $path;

                continue;
            }

            info(sprintf('Theme created for the %s panel at %s.', $panelId, $path));
        }
    }

    protected function patchFilamentThemes(): void
    {
        $files = FilamentThemes::files();

        if ($files === []) {
            warning('No Filament theme file found — create one with `php artisan make:filament-theme` and add the Finisterre @source lines (see the README).');

            return;
        }

        // Both finisterre's own views and the flowforge Kanban board views need
        // their Tailwind utilities compiled into the host theme, otherwise the
        // task board renders unstyled.
        foreach ($files as $file) {
            $relative = $this->relativePath($file);

            if (FilamentThemes::addSources($file) === []) {
                note(sprintf('@source lines already present in %s — leaving as-is.', $relative));

                continue;
            }

            info(sprintf('Patched %s with @source for Finisterre and Flowforge views.', $relative));
        }
    }

    protected function runNpmBuild(InstallCommand $command): void
    {
        if (! confirm(label: 'Would you like to run `npm run build` now?', default: true)) {
            note('Skipped — run `npm run build` manually to compile the Filament theme.');

            return;
        }

        $process = Process::fromShellCommandline('npm run build', base_path());
        $process->setTimeout(null);
        $process->run(function(string $type, string $buffer) use ($command): void {
            $command->getOutput()->write($buffer);
        });

        if (! $process->isSuccessful()) {
            warning('npm run build failed — see the output above.');
        }
    }

    protected function printFinalSteps(): void
    {
        if ($this->panelsWithoutTheme !== []) {
            warning('The task board will render unstyled until these panels get a Filament theme with the Finisterre @source lines:');

            foreach ($this->panelsWithoutTheme as $panelId => $path) {
                note(sprintf('  • %s — `php artisan make:filament-theme %s` creates %s, then `php artisan finisterre:install` adds the lines and builds it.', $panelId, $panelId, $path));
            }
        }

        outro('Finisterre install complete. Reload your Filament panel.');
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

    /**
     * Laravel's notifications table, published only when the host has none —
     * see DependencyMigrations.
     */
    public function registerNotificationsMigration(): void
    {
        $this->publishes([
            __DIR__ . '/../database/dependencies/create_notifications_table.php.stub' => database_path('migrations/' . date('Y_m_d_His') . '_create_notifications_table.php'),
        ], DependencyMigrations::NOTIFICATIONS_TAG);
    }

    public function packageBooted(): void
    {
        // Make the package's settings migration discoverable by `migrate` and
        // register the settings class so spatie/laravel-settings can cache it.
        $this->loadMigrationsFrom(__DIR__ . '/../database/settings');
        config()->push('settings.settings', FinisterreSettings::class);

        $this->registerNotificationsMigration();

        // Let the database settings override the config-file defaults.
        SettingsConfig::apply();

        if (class_exists(Livewire::class)) {
            Livewire::component('finisterre-comments', FinisterreCommentsComponent::class);
            Livewire::component('filter-tasks', FilterTasks::class);
            Livewire::component('finisterre-subtasks', FinisterreSubtasksComponent::class);
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

        // A private attachments disk sits outside public/, so its files are served
        // by routes that check the viewer may see the task they belong to.
        FilamentRouteController::registerForPrivateDisk();

        $this->observeMedia();
        $this->registerBoardCardView();

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang');

        // this will get copied to the project's public folder when
        // running php artisan filament:assets
        if (class_exists(FilamentAsset::class) && class_exists(Css::class)) {
            FilamentAsset::register(
                [Css::make('finisterre-styles', __DIR__ . '/../resources/css/app.css')],
                package: 'arzcode/finisterre'
            );
        }

        // remember to run php artisan filament:assets after changing assets in the site
    }

    /**
     * Watch the media library for attachments added to or removed from a task.
     *
     * The model is the host application's (media-library lets it be swapped), and the
     * observer bails out on every row that is not a task attachment, so this is a
     * narrow hook rather than a claim on the host's media.
     */
    protected function observeMedia(): void
    {
        /** @var class-string<Media> $model */
        $model = config('media-library.media_model') ?? Media::class;

        $model::observe(FinisterreMediaObserver::class);
    }

    /**
     * Render board cards from our own copy of flowforge's card view.
     *
     * A card image has to sit above the card title, and flowforge renders that title
     * itself with no setting to reach in front of it, so the view is replaced whole.
     * The replacement goes in front of flowforge's own directory on the `flowforge::`
     * namespace rather than under a Blade alias of ours, because Blade resolves a
     * component *alias* while it compiles the parent view: an alias swap would be
     * baked into flowforge's already-compiled column view, which never changes when
     * this package is upgraded, so the old card would keep rendering until somebody
     * ran `view:clear`. A view name resolves through the finder on every render
     * instead, so the swap survives a stale compiled view. Only card.blade.php lives
     * in that directory; every other flowforge view still falls through.
     *
     * An application that published its own copy of the card keeps it: overriding a
     * host's deliberate override would be the rude way round.
     */
    protected function registerBoardCardView(): void
    {
        if ($this->hostOverridesBoardCard()) {
            return;
        }

        View::prependNamespace('flowforge', __DIR__ . '/../resources/views/vendor/flowforge');
    }

    protected function hostOverridesBoardCard(): bool
    {
        foreach ((array)config('view.paths', []) as $path) {
            if (is_file($path . '/vendor/flowforge/livewire/card.blade.php')) {
                return true;
            }
        }

        return false;
    }
}
