<?php

use Arzcode\Finisterre\Commands\UpdateCommand;
use Arzcode\Finisterre\FinisterreServiceProvider;
use Arzcode\Finisterre\Support\DependencyMigrations;
use Arzcode\Finisterre\Support\PackageMigrations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Tags\TagsServiceProvider;

beforeEach(function() {
    // The package provider isn't registered in the test app, so register the
    // command on its own — that is all these tests exercise.
    $this->app[Kernel::class]->registerCommand(new UpdateCommand);

    // The spatie tables tasks lean on. Present unless a test drops them, so
    // the checks below only see what each test is about.
    foreach (['tags', 'taggables', 'media'] as $table) {
        if (! Schema::hasTable($table)) {
            Schema::create($table, fn(Blueprint $table) => $table->id());
        }
    }

    $this->dropDependencyTables = function(): void {
        foreach (['tags', 'taggables', 'media'] as $table) {
            Schema::dropIfExists($table);
        }
    };

    $this->migrationsPath = database_path('migrations');
    $this->schemaPath = database_path('schema');

    foreach ([$this->migrationsPath, $this->schemaPath] as $path) {
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }

    foreach (glob($this->schemaPath . '/*') ?: [] as $file) {
        unlink($file);
    }

    // A schema dump as `schema:dump --prune` leaves it: no migration files, the
    // rows of the migrations table appended to the SQL.
    $this->dumpSchema = function(array $migrations): void {
        $rows = implode(',', array_map(fn(string $migration): string => "('" . $migration . "',1)", $migrations));

        file_put_contents(
            $this->schemaPath . '/testing-schema.sql',
            'INSERT INTO migrations (migration, batch) VALUES ' . $rows . ";\n"
        );
    };

    $this->squashEverything = fn() => ($this->dumpSchema)(array_map(
        fn(string $name): string => '2024_05_05_000000_' . $name,
        FinisterreServiceProvider::migrationNames()
    ));
});

beforeEach(function() {
    // Already on a private disk, so the update has nothing to say about it; the
    // tests about the disk put the public one back.
    config()->set('filesystems.disks.finisterre', ['driver' => 'local', 'root' => storage_path('app/finisterre-files')]);
    config()->set('finisterre.attachments_disk', 'finisterre');
});

afterEach(function() {
    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }

    foreach (glob($this->schemaPath . '/*') ?: [] as $file) {
        unlink($file);
    }
});

it('counts attachments on the public disk as outstanding', function() {
    config()->set('finisterre.attachments_disk', 'public');
    ($this->squashEverything)();

    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain('Attachments are stored on the public disk')
        ->assertFailed();
});

it('fails the check when attachments_disk names a disk that does not exist', function() {
    config()->set('filesystems.disks.finisterre', null);
    ($this->squashEverything)();

    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain('has no disk by that name')
        ->assertFailed();
});

it('keeps the public disk when the prompt is declined', function() {
    config()->set('finisterre.attachments_disk', 'public');
    ($this->squashEverything)();

    $this->artisan('finisterre:update')
        ->expectsConfirmation('Move the attachments to a private disk now?', 'no')
        ->expectsConfirmation('Re-publish the Filament assets (`php artisan filament:assets`)?', 'no')
        ->expectsConfirmation('Run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(config('finisterre.attachments_disk'))->toBe('public');
});

it('switches to the private disk when asked', function() {
    config()->set('finisterre.attachments_disk', 'public');
    config()->set('filesystems.disks.finisterre', null);
    ($this->squashEverything)();

    // Both files live in the testbench skeleton, which every later test reuses.
    // The package provider is not registered here, so the config file is copied
    // in by hand the way `vendor:publish` would.
    $filesystems = config_path('filesystems.php');
    $original = (string)file_get_contents($filesystems);
    $finisterre = config_path('finisterre.php');
    copy(__DIR__ . '/../../config/finisterre.php', $finisterre);

    try {
        $this->artisan('finisterre:update')
            ->expectsConfirmation('Move the attachments to a private disk now?', 'yes')
            ->expectsConfirmation('Re-publish the Filament assets (`php artisan filament:assets`)?', 'no')
            ->expectsConfirmation('Run `npm run build` now?', 'no')
            ->assertSuccessful();

        expect(file_get_contents($filesystems))->toContain("'finisterre' => [")
            ->and(file_get_contents($finisterre))->toContain("'attachments_disk' => 'finisterre', // finisterre")
            ->and(config('finisterre.attachments_disk'))->toBe('finisterre')
            ->and(config('filesystems.disks.finisterre.root'))->toBe(storage_path('app/finisterre-files'));
    } finally {
        file_put_contents($filesystems, $original);
        @unlink($finisterre);
    }
});

it('fails the check when migrations are still unpublished', function() {
    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain('are not published yet')
        ->assertFailed();
});

it('reports nothing outstanding once every migration is published and run', function() {
    foreach (FinisterreServiceProvider::migrationNames() as $index => $name) {
        file_put_contents(
            sprintf('%s/2026_01_01_%06d_%s.php', $this->migrationsPath, $index, $name),
            "<?php\n"
        );
    }

    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain('Every migration shipped by this version is published')
        ->assertSuccessful();

    expect(PackageMigrations::unpublished())->toBe([]);
});

it('leaves everything untouched when the prompts are declined', function() {
    $this->artisan('finisterre:update')
        ->expectsConfirmation('Publish the missing migrations now?', 'no')
        ->expectsConfirmation('Re-publish the Filament assets (`php artisan filament:assets`)?', 'no')
        ->expectsConfirmation('Run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(PackageMigrations::unpublished())->toBe(FinisterreServiceProvider::migrationNames());
});

it('passes the check when every migration was squashed into the schema dump', function() {
    ($this->squashEverything)();

    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain("already part of this application's schema")
        ->expectsOutputToContain('Every migration shipped by this version is published')
        ->assertSuccessful();

    expect(PackageMigrations::unpublished())->toBe([]);
});

it('still asks to publish the migrations a squashed schema does not cover', function() {
    ($this->dumpSchema)(['2024_05_05_000000_create_finisterre_tables']);

    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain('are not published yet')
        ->assertFailed();

    expect(PackageMigrations::unpublished())
        ->not->toContain('create_finisterre_tables')
        ->toContain('create_finisterre_subtasks_table');
});

it('fails the check while a squashed migration sits republished under a new name', function() {
    ($this->squashEverything)();
    file_put_contents($this->migrationsPath . '/2026_09_09_000000_create_finisterre_tables.php', "<?php\n");

    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain('already ran under a different name')
        ->assertFailed();
});

it('deletes a republished copy of a squashed migration when asked', function() {
    ($this->squashEverything)();
    $duplicate = $this->migrationsPath . '/2026_09_09_000000_create_finisterre_tables.php';
    file_put_contents($duplicate, "<?php\n");

    $this->artisan('finisterre:update')
        ->expectsConfirmation('Delete these duplicate migration files?', 'yes')
        ->expectsConfirmation('Re-publish the Filament assets (`php artisan filament:assets`)?', 'no')
        ->expectsConfirmation('Run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(file_exists($duplicate))->toBeFalse();
    expect(PackageMigrations::republished())->toBe([]);
});

it('keeps a republished copy when the prompt is declined', function() {
    ($this->squashEverything)();
    $duplicate = $this->migrationsPath . '/2026_09_09_000000_create_finisterre_tables.php';
    file_put_contents($duplicate, "<?php\n");

    $this->artisan('finisterre:update')
        ->expectsConfirmation('Delete these duplicate migration files?', 'no')
        ->expectsConfirmation('Re-publish the Filament assets (`php artisan filament:assets`)?', 'no')
        ->expectsConfirmation('Run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(file_exists($duplicate))->toBeTrue();
});

it('fails the check when the tags and media tables are missing with no migration published', function() {
    ($this->dropDependencyTables)();
    ($this->squashEverything)();

    $this->artisan('finisterre:update', ['--check' => true])
        ->expectsOutputToContain('Finisterre relies on are missing')
        ->expectsOutputToContain('media — spatie/laravel-medialibrary (task attachments)')
        ->assertFailed();
});

it('does not ask for a dependency migration that is published but not run yet', function() {
    ($this->dropDependencyTables)();
    ($this->squashEverything)();
    file_put_contents($this->migrationsPath . '/2026_01_01_000000_create_tag_tables.php', "<?php\n");
    file_put_contents($this->migrationsPath . '/2026_01_01_000001_create_media_table.php', "<?php\n");

    $this->artisan('finisterre:update', ['--check' => true])
        ->doesntExpectOutputToContain('Finisterre relies on are missing')
        ->expectsOutputToContain('have not run yet')
        ->expectsOutputToContain('create_tag_tables')
        ->assertFailed();
});

it('publishes the dependency migrations when asked', function() {
    ($this->dropDependencyTables)();
    ($this->squashEverything)();

    // The spatie providers register their publishable migrations; the plain
    // test app leaves them out like it leaves Finisterre's own provider out.
    $this->app->register(TagsServiceProvider::class);
    $this->app->register(MediaLibraryServiceProvider::class);

    $this->artisan('finisterre:update')
        ->expectsConfirmation('Publish the missing dependency migrations now?', 'yes')
        ->expectsConfirmation('Run the migrations now?', 'no')
        ->expectsConfirmation('Re-publish the Filament assets (`php artisan filament:assets`)?', 'no')
        ->expectsConfirmation('Run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(PackageMigrations::publishedFile('create_tag_tables'))->not->toBeNull()
        ->and(PackageMigrations::publishedFile('create_media_table'))->not->toBeNull()
        ->and(DependencyMigrations::missing())->toBe([]);
});

it('leaves the dependency migrations unpublished when the prompt is declined', function() {
    ($this->dropDependencyTables)();
    ($this->squashEverything)();

    $this->artisan('finisterre:update')
        ->expectsConfirmation('Publish the missing dependency migrations now?', 'no')
        ->expectsOutputToContain('--provider="Spatie\\Tags\\TagsServiceProvider" --tag="tags-migrations"')
        ->expectsConfirmation('Re-publish the Filament assets (`php artisan filament:assets`)?', 'no')
        ->expectsConfirmation('Run `npm run build` now?', 'no')
        ->assertSuccessful();

    expect(DependencyMigrations::missing())->toHaveCount(2);
});
