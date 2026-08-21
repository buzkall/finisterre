<?php

use Illuminate\Support\Facades\Schema;

function normalizeMigration()
{
    return include __DIR__ . '/../../database/migrations/normalize_finisterre_table_names.php.stub';
}

it('renames tables customized through the old config keys back to the canonical names', function() {
    Schema::rename('finisterre_tasks', 'host_tasks');
    config()->set('finisterre.table_name', 'host_tasks');

    normalizeMigration()->up();

    expect(Schema::hasTable('finisterre_tasks'))->toBeTrue()
        ->and(Schema::hasTable('host_tasks'))->toBeFalse();
});

it('leaves canonical tables untouched when no custom names were configured', function() {
    normalizeMigration()->up();

    expect(Schema::hasTable('finisterre_tasks'))->toBeTrue()
        ->and(Schema::hasTable('finisterre_task_comments'))->toBeTrue();
});

it('does not rename when the canonical table already exists', function() {
    Schema::create('host_tasks', function($table) {
        $table->id();
    });
    config()->set('finisterre.table_name', 'host_tasks');

    normalizeMigration()->up();

    expect(Schema::hasTable('finisterre_tasks'))->toBeTrue()
        ->and(Schema::hasTable('host_tasks'))->toBeTrue();

    Schema::drop('host_tasks');
});
