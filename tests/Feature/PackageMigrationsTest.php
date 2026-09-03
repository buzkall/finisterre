<?php

use Arzcode\Finisterre\FinisterreServiceProvider;
use Arzcode\Finisterre\Support\PackageMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function() {
    $this->migrationsPath = database_path('migrations');
    $this->schemaPath = database_path('schema');

    foreach ([$this->migrationsPath, $this->schemaPath] as $path) {
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    // Start from a clean slate: another test's leftovers would look like
    // published migrations here.
    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }

    foreach (glob($this->schemaPath . '/*') ?: [] as $file) {
        unlink($file);
    }

    $this->publish = function(string $name, string $timestamp = '2026_01_01_000000'): string {
        $path = $this->migrationsPath . '/' . $timestamp . '_' . $name . '.php';
        file_put_contents($path, "<?php\n");

        return $path;
    };

    // A schema dump as `schema:dump` writes it: the migrations table's rows are
    // appended to the SQL so a fresh database gets them back.
    $this->dumpSchema = function(array $migrations): string {
        $rows = implode(',', array_map(fn(string $migration): string => "('" . $migration . "',1)", $migrations));
        $path = $this->schemaPath . '/testing-schema.sql';
        file_put_contents($path, "CREATE TABLE finisterre_tasks (id integer);\nINSERT INTO migrations (migration, batch) VALUES " . $rows . ";\n");

        return $path;
    };
});

afterEach(function() {
    foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
        unlink($file);
    }

    foreach (glob($this->schemaPath . '/*') ?: [] as $file) {
        unlink($file);
    }
});

function createMigrationsTable(): void
{
    Schema::create('migrations', function($table) {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });
}

it('reports every shipped migration as unpublished when nothing was published', function() {
    expect(PackageMigrations::unpublished())->toBe(FinisterreServiceProvider::migrationNames());
});

it('only reports the migrations that are still missing', function() {
    ($this->publish)('create_finisterre_tables');

    expect(PackageMigrations::unpublished())
        ->not->toContain('create_finisterre_tables')
        ->toContain('create_finisterre_subtasks_table');
});

it('matches a published migration through its timestamp prefix', function() {
    $path = ($this->publish)('add_subtasks_to_finisterre_tasks', '2026_02_03_101112');

    $status = collect(PackageMigrations::status())->firstWhere('name', 'add_subtasks_to_finisterre_tasks');

    expect($status['file'])->toBe($path);
});

it('lists published migrations that have not run yet as pending', function() {
    ($this->publish)('create_finisterre_tables');
    createMigrationsTable();

    expect(PackageMigrations::pending())->toContain('2026_01_01_000000_create_finisterre_tables');

    Schema::drop('migrations');
});

it('does not list a migration as pending once the migrations table records it', function() {
    ($this->publish)('create_finisterre_tables');
    createMigrationsTable();

    DB::table('migrations')->insert([
        'migration' => '2026_01_01_000000_create_finisterre_tables',
        'batch'     => 1,
    ]);

    expect(PackageMigrations::pending())->toBe([]);

    Schema::drop('migrations');
});

it('reports the migrated state as unknown while the migrations table is missing', function() {
    ($this->publish)('create_finisterre_tables');

    $status = collect(PackageMigrations::status())->firstWhere('name', 'create_finisterre_tables');

    expect($status['migrated'])->toBeNull();
});

it('treats a migration recorded in the migrations table with no file as squashed, not missing', function() {
    createMigrationsTable();

    DB::table('migrations')->insert([
        'migration' => '2024_05_05_000000_create_finisterre_tables',
        'batch'     => 1,
    ]);

    expect(PackageMigrations::unpublished())->not->toContain('create_finisterre_tables');
    expect(PackageMigrations::squashed())->toContain('create_finisterre_tables');
    expect(PackageMigrations::pending())->toBe([]);

    $status = collect(PackageMigrations::status())->firstWhere('name', 'create_finisterre_tables');

    expect($status['file'])->toBeNull()
        ->and($status['squashed'])->toBeTrue()
        ->and($status['migrated'])->toBeTrue();

    Schema::drop('migrations');
});

it('treats a migration named in a schema dump as squashed even without a migrations table', function() {
    ($this->dumpSchema)(['2024_05_05_000000_create_finisterre_tables']);

    expect(PackageMigrations::squashed())->toBe(['create_finisterre_tables']);
    expect(PackageMigrations::unpublished())
        ->not->toContain('create_finisterre_tables')
        ->toContain('create_finisterre_subtasks_table');
});

it('does not mistake a migration that was never run for a squashed one', function() {
    createMigrationsTable();

    expect(PackageMigrations::squashed())->toBe([]);
    expect(PackageMigrations::unpublished())->toBe(FinisterreServiceProvider::migrationNames());

    Schema::drop('migrations');
});

it('lists a copy published under a new timestamp of an already squashed migration', function() {
    $path = ($this->publish)('create_finisterre_tables', '2026_09_09_000000');
    ($this->dumpSchema)(['2024_05_05_000000_create_finisterre_tables']);

    expect(PackageMigrations::republished())->toBe([$path]);
});

it('does not list a normally published migration as republished', function() {
    ($this->publish)('create_finisterre_tables', '2024_05_05_000000');
    ($this->dumpSchema)(['2024_05_05_000000_create_finisterre_tables']);

    expect(PackageMigrations::republished())->toBe([]);
});

it('deletes the files it is asked to remove', function() {
    $path = ($this->publish)('create_finisterre_tables');

    expect(PackageMigrations::removeFiles([$path]))->toBe(['2026_01_01_000000_create_finisterre_tables']);
    expect(file_exists($path))->toBeFalse();
});
