<?php

namespace Arzcode\Finisterre\Commands;

use Arzcode\Finisterre\FinisterreServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

class UninstallCommand extends Command
{
    public $signature = 'finisterre:uninstall';
    public $description = 'Reverse the Finisterre install: unregister the plugin/trait, clean the theme + .env, and optionally drop tables, migrations and config.';

    public function handle(): int
    {
        $this->warn('This will remove Finisterre from your application.');

        if (! $this->confirm('Do you want to continue?', false)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $steps = [
            fn() => $this->unpatchPanelProviders(),
            fn() => $this->unpatchUserModel(),
            fn() => $this->unpatchFilamentThemes(),
            fn() => $this->deactivateInEnvFile(),
            fn() => $this->deleteSettings(),
            fn() => $this->dropTables(),
            fn() => $this->deletePublishedMigrations(),
            fn() => $this->deletePublishedAssets(),
            fn() => $this->deleteConfigFile(),
            fn() => $this->runFinalSteps(),
        ];

        foreach ($steps as $step) {
            $this->newLine();
            $step();
        }

        return self::SUCCESS;
    }

    protected function unpatchPanelProviders(): void
    {
        $dir = app_path('Providers/Filament');

        if (! is_dir($dir)) {
            $this->warn('No app/Providers/Filament directory — nothing to clean.');

            return;
        }

        $files = glob($dir . '/*PanelProvider.php') ?: [];

        if ($files === []) {
            $this->warn('No *PanelProvider.php found — nothing to clean.');

            return;
        }

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = $this->relativePath($file);

            if (! str_contains($contents, 'FinisterrePlugin')) {
                $this->line(sprintf('FinisterrePlugin not present in %s — skipping.', $relative));

                continue;
            }

            $patched = $this->removeLinesContaining($contents, 'FinisterrePlugin::make()');
            $patched = $this->removeUseImport($patched, 'Arzcode\Finisterre\FinisterrePlugin');

            file_put_contents($file, $patched);
            $this->info(sprintf('Removed FinisterrePlugin from %s.', $relative));
        }
    }

    protected function unpatchUserModel(): void
    {
        $path = app_path('Models/User.php');
        $relative = $this->relativePath($path);

        if (! file_exists($path)) {
            $this->warn(sprintf('%s not found — nothing to clean.', $relative));

            return;
        }

        $contents = (string)file_get_contents($path);

        if (! str_contains($contents, 'FinisterreUserTrait')) {
            $this->line(sprintf('FinisterreUserTrait not present in %s — skipping.', $relative));

            return;
        }

        $patched = preg_replace('/^[ \t]*use\s+FinisterreUserTrait\s*;[ \t]*\r?\n/m', '', $contents) ?? $contents;
        $patched = $this->removeUseImport($patched, 'Arzcode\Finisterre\Traits\FinisterreUserTrait');

        if (str_contains($patched, 'FinisterreUserTrait')) {
            $this->warn(sprintf('FinisterreUserTrait still referenced in %s — remove it manually (it may be grouped with other traits).', $relative));
        }

        file_put_contents($path, $patched);
        $this->info(sprintf('Removed FinisterreUserTrait from %s.', $relative));
    }

    protected function unpatchFilamentThemes(): void
    {
        $files = glob(resource_path('css/filament/*/theme.css')) ?: [];

        if ($files === []) {
            $this->warn('No theme.css under resources/css/filament/*/theme.css — nothing to clean.');

            return;
        }

        $markers = [
            'arzcode/finisterre/resources/views',
            'relaticle/flowforge/resources/views',
        ];

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = $this->relativePath($file);

            if (! str_contains($contents, 'arzcode/finisterre/resources/views') && ! str_contains($contents, 'relaticle/flowforge/resources/views')) {
                $this->line(sprintf('No Finisterre @source in %s — skipping.', $relative));

                continue;
            }

            $patched = $contents;
            foreach ($markers as $marker) {
                $patched = $this->removeLinesContaining($patched, $marker);
            }

            file_put_contents($file, rtrim($patched, "\n") . "\n");
            $this->info(sprintf('Removed Finisterre @source from %s.', $relative));
        }
    }

    protected function deactivateInEnvFile(): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->warn('No .env file found — nothing to clean.');

            return;
        }

        $contents = (string)file_get_contents($envPath);

        // FINISTERRE_ACTIVE is the legacy on/off flag; FINISTERRE_ENVIRONMENTS replaced it.
        if (! preg_match('/^FINISTERRE_(ACTIVE|ENVIRONMENTS)=/m', $contents)) {
            $this->line('No Finisterre .env entries present — skipping.');

            return;
        }

        $patched = preg_replace('/^FINISTERRE_(ACTIVE|ENVIRONMENTS)=.*\r?\n?/m', '', $contents) ?? $contents;
        file_put_contents($envPath, $patched);
        $this->info('Removed Finisterre entries from .env');
    }

    protected function deleteSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            $this->line('No settings table found — skipping.');

            return;
        }

        $deleted = DB::table('settings')->where('group', 'finisterre')->delete();

        if ($deleted === 0) {
            $this->line('No Finisterre settings stored — skipping.');

            return;
        }

        $this->info(sprintf('Removed %d Finisterre setting(s) from the settings table.', $deleted));
    }

    protected function dropTables(): void
    {
        if (! $this->confirm('Would you like to drop the Finisterre database tables? This permanently deletes all task data.', false)) {
            $this->line('Skipped — Finisterre tables left in place.');

            return;
        }

        $tables = [
            config('finisterre.task_changes_table_name', 'finisterre_task_changes'),
            config('finisterre.comments.table_name', 'finisterre_task_comments'),
            config('finisterre.table_name', 'finisterre_tasks'),
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
            $this->line("Dropped {$table}");
        }

        $this->info('Finisterre tables dropped.');
        $this->warn('Their rows remain in the `migrations` table; remove them manually if you also delete the migration files.');
    }

    protected function deletePublishedMigrations(): void
    {
        // The published migrations carry a timestamp prefix and not all of them
        // contain "finisterre" in the file name (e.g. add_task_changes_table),
        // so match every known migration base name instead of a single glob.
        $names = [...FinisterreServiceProvider::migrationNames(), 'create_finisterre_settings'];

        $files = [];
        foreach ($names as $name) {
            foreach (glob(database_path('migrations/*' . $name . '.php')) ?: [] as $file) {
                $files[$file] = $file;
            }
        }
        $files = array_values($files);

        if ($files === []) {
            $this->line('No published Finisterre migrations found — skipping.');

            return;
        }

        if (! $this->confirm(sprintf('Delete %d published Finisterre migration file(s)?', count($files)), false)) {
            $this->line('Skipped — published migrations left in place.');

            return;
        }

        foreach ($files as $file) {
            @unlink($file);
            $this->line('Deleted ' . $this->relativePath($file));
        }

        $this->info('Published migrations deleted.');
    }

    protected function deletePublishedAssets(): void
    {
        // Assets published by `php artisan filament:assets` into the host's
        // public/ folder, named after the owning package. Removing the package
        // won't clean these, so delete them explicitly.
        $dirs = [
            public_path('css/arzcode/finisterre'),
            public_path('js/arzcode/finisterre'),
            public_path('js/relaticle/flowforge'),
            public_path('css/relaticle/flowforge'),
        ];

        $existing = array_values(array_filter($dirs, 'is_dir'));

        if ($existing === []) {
            $this->line('No published Finisterre assets found — skipping.');

            return;
        }

        foreach ($existing as $dir) {
            $this->deleteDirectory($dir);
            $this->line('Deleted ' . $this->relativePath($dir));
        }

        $this->info('Published assets deleted.');
    }

    protected function deleteDirectory(string $dir): void
    {
        $entries = glob(rtrim($dir, '/') . '/*') ?: [];

        foreach ($entries as $entry) {
            is_dir($entry) ? $this->deleteDirectory($entry) : @unlink($entry);
        }

        @rmdir($dir);
    }

    protected function deleteConfigFile(): void
    {
        $path = config_path('finisterre.php');

        if (! file_exists($path)) {
            $this->line('No published config file found — skipping.');

            return;
        }

        if (! $this->confirm('Delete the published config/finisterre.php file?', false)) {
            $this->line('Skipped — config file left in place.');

            return;
        }

        @unlink($path);
        $this->info('Deleted config/finisterre.php');
    }

    protected function runFinalSteps(): void
    {
        $commands = [
            'composer remove arzcode/finisterre' => 'Removing the arzcode/finisterre package…',
            'npm run build'                      => 'Recompiling your Filament theme without Finisterre…',
        ];

        foreach ($commands as $command => $message) {
            $this->info($message);

            $result = Process::path(base_path())
                ->forever()
                ->run($command, function(string $type, string $output): void {
                    $this->output->write($output);
                });

            if (! $result->successful()) {
                $this->error(sprintf('`%s` failed — run it manually to finish the uninstall.', $command));

                return;
            }
        }

        $this->info('Finisterre uninstall complete.');
    }

    protected function removeLinesContaining(string $contents, string $needle): string
    {
        $lines = preg_split('/(?<=\n)/', $contents) ?: [];
        $kept = array_filter($lines, fn(string $line): bool => ! str_contains($line, $needle));

        return implode('', $kept);
    }

    protected function removeUseImport(string $contents, string $fqcn): string
    {
        $pattern = '/^use\s+' . preg_quote($fqcn, '/') . ';[ \t]*\r?\n/m';

        return preg_replace($pattern, '', $contents) ?? $contents;
    }

    protected function relativePath(string $absolutePath): string
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($absolutePath, $base) ? substr($absolutePath, strlen($base)) : $absolutePath;
    }
}
