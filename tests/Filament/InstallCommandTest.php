<?php

use Arzcode\Finisterre\Support\DependencyMigrations;
use Arzcode\Finisterre\Support\PackageMigrations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * The install command has no class of its own: spatie/laravel-package-tools
 * assembles it from what `FinisterreServiceProvider::configurePackage()`
 * declares, so this is where the installation steps are covered.
 *
 * It runs against a booted panel because the theme step asks Filament which
 * panels are registered.
 */
beforeEach(function() {
    $this->migrationsPath = database_path('migrations');

    File::ensureDirectoryExists($this->migrationsPath);

    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }

    // The suite creates these by hand so the pages under test can query them.
    // Drop them here: an application installing Finisterre for the first time
    // has neither the tables nor the migrations that create them.
    foreach (['taggables', 'tags', 'media', 'notifications'] as $table) {
        Schema::dropIfExists($table);
    }

    $this->themesPath = resource_path('css/filament');

    File::deleteDirectory($this->themesPath);

    // The prompts every install asks before reaching the steps under test. The
    // board-slug prompt is not among them: reading the settings throws in this
    // environment, and that step swallows it. Publishing the spatie migrations
    // is not among them either, by design — a table the package cannot work
    // without is not a question.
    $this->install = fn() => $this->artisan('finisterre:install')
        ->expectsConfirmation('Would you like to publish the config file?', 'no')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no');

    // Already on a private disk, so the install does not ask about it; the test
    // about that prompt puts the public disk back.
    config()->set('filesystems.disks.finisterre', ['driver' => 'local', 'root' => storage_path('app/finisterre-files')]);
    config()->set('finisterre.attachments_disk', 'finisterre');
});

afterEach(function() {
    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }

    File::deleteDirectory($this->themesPath);
});

it('switches attachments to a private disk when asked', function() {
    config()->set('finisterre.attachments_disk', 'public');
    config()->set('filesystems.disks.finisterre', null);

    // Both files live in the testbench skeleton, which every later test reuses.
    $filesystems = config_path('filesystems.php');
    $original = (string)file_get_contents($filesystems);
    $finisterre = config_path('finisterre.php');

    try {
        ($this->install)()
            ->expectsConfirmation('Store attachments on a private disk, served only to users who can see their task?', 'yes')
            ->expectsConfirmation('The admin panel has no theme at resources/css/filament/admin/theme.css. Create one now with `php artisan make:filament-theme admin`?', 'no')
            ->expectsConfirmation('Would you like to run `npm run build` now?', 'no')
            ->assertSuccessful();

        expect(file_get_contents($filesystems))->toContain("'finisterre' => [")
            // The config file prompt was declined, so the switch published it.
            ->and(file_get_contents($finisterre))->toContain("'attachments_disk' => 'finisterre'")
            ->and(config('finisterre.attachments_disk'))->toBe('finisterre')
            ->and(config('filesystems.disks.finisterre.root'))->toBe(storage_path('app/finisterre-files'));
    } finally {
        file_put_contents($filesystems, $original);
        @unlink($finisterre);
    }
});

it('publishes the tags, media and notifications migrations an application does not have yet', function() {
    ($this->install)()
        ->expectsConfirmation('The admin panel has no theme at resources/css/filament/admin/theme.css. Create one now with `php artisan make:filament-theme admin`?', 'no')
        ->expectsConfirmation('Would you like to run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(PackageMigrations::publishedFile('create_tag_tables'))->not->toBeNull()
        ->and(PackageMigrations::publishedFile('create_media_table'))->not->toBeNull()
        ->and(PackageMigrations::publishedFile('create_notifications_table'))->not->toBeNull()
        ->and(DependencyMigrations::missing())->toBe([]);
});

it('leaves the dependency migrations alone when the tables are already there', function() {
    Schema::create('tags', fn($table) => $table->id());
    Schema::create('taggables', fn($table) => $table->id());
    Schema::create('media', fn($table) => $table->id());
    Schema::create('notifications', fn($table) => $table->id());

    ($this->install)()
        ->expectsOutputToContain('The tags, media and notifications tables Finisterre relies on are in place')
        ->expectsConfirmation('The admin panel has no theme at resources/css/filament/admin/theme.css. Create one now with `php artisan make:filament-theme admin`?', 'no')
        ->expectsConfirmation('Would you like to run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(PackageMigrations::publishedFile('create_tag_tables'))->toBeNull();
});

it('warns at the end when a panel was left without a theme instead of reporting a clean install', function() {
    ($this->install)()
        ->expectsConfirmation('The admin panel has no theme at resources/css/filament/admin/theme.css. Create one now with `php artisan make:filament-theme admin`?', 'no')
        ->expectsConfirmation('Would you like to run `npm run build` now?', 'no')
        ->expectsOutputToContain('The task board will render unstyled until these panels get a Filament theme')
        ->assertSuccessful();
});

it('adds the @source lines to a theme that already exists, without asking to create one', function() {
    $theme = resource_path('css/filament/admin/theme.css');

    File::ensureDirectoryExists(dirname($theme));
    file_put_contents($theme, "@import '../../../../vendor/filament/filament/resources/css/theme.css';\n");

    ($this->install)()
        ->expectsConfirmation('Would you like to run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(file_get_contents($theme))
        ->toContain("@source '../../../../vendor/arzcode/finisterre/resources/views/**/*.blade.php';")
        ->toContain("@source '../../../../vendor/relaticle/flowforge/resources/views/**/*.blade.php';");
});
