<?php

namespace Arzcode\Finisterre\Commands;

use Arzcode\Finisterre\Support\PackageMigrations;
use Arzcode\Finisterre\Support\SettingsConfig;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class UpdateCommand extends Command
{
    public $signature = 'finisterre:update {--check : Report what is outstanding without publishing, migrating or seeding anything}';
    public $description = 'After upgrading the package: show which migrations still need publishing or running, seed new settings and refresh the published assets.';

    public function handle(): int
    {
        $check = (bool)$this->option('check');

        intro('Finisterre update' . $this->installedVersion());

        $this->showMigrationTable();

        $outstanding = 0;

        $steps = [
            fn(): int => $this->handleUnpublishedMigrations($check),
            fn(): int => $this->handlePendingMigrations($check),
            fn(): int => $this->handleMissingSettings($check),
            fn(): int => $this->reportConfigKeys(),
            fn(): int => $this->reportThemeSources(),
        ];

        foreach ($steps as $step) {
            $this->newLine();
            $outstanding += $step();
        }

        if ($check) {
            $this->newLine();

            if ($outstanding > 0) {
                warning(sprintf('%d item(s) outstanding — run `php artisan finisterre:update` to apply them.', $outstanding));

                return self::FAILURE;
            }

            info('Finisterre is up to date.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->refreshAssets();

        $this->newLine();
        $this->rebuildTheme();

        outro('Finisterre update complete.');

        return self::SUCCESS;
    }

    protected function installedVersion(): string
    {
        try {
            if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('arzcode/finisterre')) {
                return ' ' . (string)InstalledVersions::getPrettyVersion('arzcode/finisterre');
            }
        } catch (Throwable) {
            // Fall through to an unversioned title.
        }

        return '';
    }

    protected function showMigrationTable(): void
    {
        $rows = array_map(fn(array $migration): array => [
            $migration['name'],
            $migration['file'] === null
                ? '<fg=yellow>not published</>'
                : basename((string)$migration['file']),
            match ($migration['migrated']) {
                true    => '<fg=green>yes</>',
                false   => '<fg=yellow>no</>',
                default => '<fg=gray>unknown</>',
            },
        ], PackageMigrations::status());

        table(['Migration', 'Published as', 'Migrated'], $rows);
    }

    protected function handleUnpublishedMigrations(bool $check): int
    {
        $missing = PackageMigrations::unpublished();

        if ($missing === []) {
            info('Every migration shipped by this version is published.');

            return 0;
        }

        warning(sprintf('%d migration(s) shipped by this version are not published yet:', count($missing)));
        note($this->bulletList($missing));

        if ($check) {
            return count($missing);
        }

        if (! confirm(label: 'Publish the missing migrations now?', default: true)) {
            note('Skipped — publish them later with `php artisan vendor:publish --tag=finisterre-migrations`.');

            return 0;
        }

        // Publishing is idempotent: already-published migrations keep the file
        // name they got the first time instead of being copied again under a
        // fresh timestamp.
        $this->callSilently('vendor:publish', ['--tag' => 'finisterre-migrations']);

        $stillMissing = PackageMigrations::unpublished();

        if ($stillMissing !== []) {
            warning('Some migrations could not be published:');
            note($this->bulletList($stillMissing));

            return count($stillMissing);
        }

        info('Missing migrations published.');

        return 0;
    }

    protected function handlePendingMigrations(bool $check): int
    {
        $pending = PackageMigrations::pending();

        if ($pending === []) {
            return 0;
        }

        warning(sprintf('%d published migration(s) have not run yet:', count($pending)));
        note($this->bulletList($pending));

        if ($check) {
            return count($pending);
        }

        if (! confirm(label: 'Run the migrations now?', default: true)) {
            note('Skipped — run `php artisan migrate` when you are ready.');

            return 0;
        }

        $this->call('migrate');

        return 0;
    }

    protected function handleMissingSettings(bool $check): int
    {
        try {
            $missing = SettingsConfig::missing();
        } catch (Throwable) {
            warning('Could not read the settings table — run the migrations first, then re-run this command.');

            return 0;
        }

        if ($missing === []) {
            info('Every Finisterre setting has a stored value.');

            return 0;
        }

        warning(sprintf('%d setting(s) added by this version have no stored value yet:', count($missing)));
        note($this->bulletList($missing));

        if ($check) {
            return count($missing);
        }

        if (! confirm(label: 'Seed them from the config defaults now?', default: true)) {
            note('Skipped — the config-file defaults are used until the rows exist.');

            return 0;
        }

        try {
            $created = SettingsConfig::seedMissing();
            info(sprintf('%d setting(s) seeded — adjust them from the settings page.', $created));
        } catch (Throwable) {
            warning('Could not seed the settings — check the settings table and try again.');
        }

        return 0;
    }

    /**
     * Report config keys that this version added or dropped. Purely
     * informational: the service provider deep-merges the published file over
     * the package defaults, so a published config missing a new key still
     * resolves to that key's default.
     */
    protected function reportConfigKeys(): int
    {
        $path = config_path('finisterre.php');

        if (! file_exists($path)) {
            note('No published config file — the package defaults are used as-is.');

            return 0;
        }

        $packageKeys = $this->configKeys(require __DIR__ . '/../../config/finisterre.php');
        $publishedKeys = $this->configKeys(require $path);

        $added = array_values(array_diff($packageKeys, $publishedKeys));
        $removed = array_values(array_diff($publishedKeys, $packageKeys));

        if ($added === [] && $removed === []) {
            info('config/finisterre.php is in sync with this version.');

            return 0;
        }

        if ($added !== []) {
            note(sprintf(
                "This version adds %d config key(s) your published config/finisterre.php doesn't have:\n%s\n\nThey fall back to the package defaults, so copying them over is optional.",
                count($added),
                $this->bulletList($added)
            ));
        }

        if ($removed !== []) {
            note(sprintf(
                "Your published config/finisterre.php has %d key(s) this version no longer uses:\n%s",
                count($removed),
                $this->bulletList($removed)
            ));
        }

        return 0;
    }

    /**
     * Dotted keys of a config array. Recursion stops at list arrays, whose
     * numeric indexes say nothing about which keys a config file declares.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function configKeys(array $config, string $prefix = ''): array
    {
        $keys = [];

        foreach ($config as $key => $value) {
            $dotted = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            $keys[] = $dotted;

            if (is_array($value) && ! array_is_list($value)) {
                $keys = [...$keys, ...$this->configKeys($value, $dotted)];
            }
        }

        return $keys;
    }

    /**
     * A new release can start rendering views from another vendor directory, so
     * check the host theme still points Tailwind at every directory we need.
     */
    protected function reportThemeSources(): int
    {
        $files = glob(resource_path('css/filament/*/theme.css')) ?: [];

        if ($files === []) {
            note('No theme.css under resources/css/filament/*/theme.css — nothing to check.');

            return 0;
        }

        $markers = [
            'arzcode/finisterre/resources/views',
            'relaticle/flowforge/resources/views',
        ];

        $incomplete = [];

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);

            foreach ($markers as $marker) {
                if (! str_contains($contents, $marker)) {
                    $incomplete[] = $this->relativePath($file) . ' — missing @source for ' . $marker;
                }
            }
        }

        if ($incomplete === []) {
            info('Every Filament theme has the Finisterre @source lines.');

            return 0;
        }

        warning('Some Filament themes are missing @source lines:');
        note($this->bulletList($incomplete) . "\n\nRun `php artisan finisterre:install` to add them back.");

        return 0;
    }

    protected function refreshAssets(): void
    {
        if (! confirm(label: 'Re-publish the Filament assets (`php artisan filament:assets`)?', default: true)) {
            note('Skipped — run `php artisan filament:assets` to pick up this version\'s assets.');

            return;
        }

        Artisan::call('filament:assets', [], $this->getOutput());
    }

    protected function rebuildTheme(): void
    {
        if (! confirm(label: 'Run `npm run build` now?', default: true)) {
            note('Skipped — run `npm run build` manually to recompile the Filament theme.');

            return;
        }

        $process = Process::fromShellCommandline('npm run build', base_path());
        $process->setTimeout(null);
        $process->run(function(string $type, string $buffer): void {
            $this->getOutput()->write($buffer);
        });

        if (! $process->isSuccessful()) {
            warning('npm run build failed — see the output above.');
        }
    }

    /**
     * @param  list<string>  $items
     */
    protected function bulletList(array $items): string
    {
        return implode(PHP_EOL, array_map(fn(string $item): string => '  • ' . $item, $items));
    }

    protected function relativePath(string $absolutePath): string
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($absolutePath, $base) ? substr($absolutePath, strlen($base)) : $absolutePath;
    }
}
