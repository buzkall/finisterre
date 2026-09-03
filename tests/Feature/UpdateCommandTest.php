<?php

use Arzcode\Finisterre\Commands\UpdateCommand;
use Arzcode\Finisterre\FinisterreServiceProvider;
use Arzcode\Finisterre\Support\PackageMigrations;
use Illuminate\Contracts\Console\Kernel;

beforeEach(function() {
    // The package provider isn't registered in the test app, so register the
    // command on its own — that is all these tests exercise.
    $this->app[Kernel::class]->registerCommand(new UpdateCommand);

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

afterEach(function() {
    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }

    foreach (glob($this->schemaPath . '/*') ?: [] as $file) {
        unlink($file);
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
        ->expectsOutputToContain('squashed into this application')
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
