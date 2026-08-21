<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function() {
    config([
        'finisterre.table_name'          => 'finisterre_tasks',
        'finisterre.subtasks.table_name' => 'finisterre_subtasks',
    ]);

    Schema::dropIfExists('finisterre_subtasks');

    // The base tables are created by TestCase; add the legacy json column
    // that this migration is meant to convert and then drop.
    if (! Schema::hasColumn('finisterre_tasks', 'subtasks')) {
        Schema::table('finisterre_tasks', function(Blueprint $table) {
            $table->json('subtasks')->nullable();
        });
    }

    DB::table('finisterre_tasks')->delete();
});

afterEach(function() {
    Schema::dropIfExists('finisterre_subtasks');

    if (Schema::hasColumn('finisterre_tasks', 'subtasks')) {
        Schema::table('finisterre_tasks', function(Blueprint $table) {
            $table->dropColumn('subtasks');
        });
    }
});

function runSubtasksMigration(): object
{
    $migration = include __DIR__ . '/../../database/migrations/create_finisterre_subtasks_table.php.stub';
    $migration->up();

    return $migration;
}

function insertLegacyTask(int $id, ?array $subtasks): void
{
    DB::table('finisterre_tasks')->insert([
        'id'         => $id,
        'title'      => 'Task ' . $id,
        'status'     => 'open',
        'priority'   => 'low',
        'subtasks'   => $subtasks === null ? null : json_encode($subtasks),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('backfills the json subtasks into the new table and drops the column', function() {
    insertLegacyTask(1, [
        ['title' => 'First', 'completed' => true],
        ['title' => 'Second', 'completed' => false],
    ]);
    insertLegacyTask(2, null);

    runSubtasksMigration();

    $rows = DB::table('finisterre_subtasks')->orderBy('order_column')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->task_id)->toBe(1)
        ->and($rows[0]->title)->toBe('First')
        ->and((bool)$rows[0]->completed)->toBeTrue()
        ->and($rows[0]->order_column)->toBe(10)
        ->and($rows[1]->title)->toBe('Second')
        ->and((bool)$rows[1]->completed)->toBeFalse()
        ->and($rows[1]->order_column)->toBe(20)
        ->and(Schema::hasColumn('finisterre_tasks', 'subtasks'))->toBeFalse();
});

it('skips the blank rows the old editor could persist', function() {
    insertLegacyTask(1, [
        ['title' => '', 'completed' => false],
        ['title' => '   ', 'completed' => false],
        ['title' => 'Real one', 'completed' => false],
    ]);

    runSubtasksMigration();

    expect(DB::table('finisterre_subtasks')->pluck('title')->all())->toBe(['Real one']);
});

it('restores the json column on rollback', function() {
    insertLegacyTask(1, [
        ['title' => 'First', 'completed' => true],
        ['title' => 'Second', 'completed' => false],
    ]);

    $migration = runSubtasksMigration();
    $migration->down();

    expect(Schema::hasTable('finisterre_subtasks'))->toBeFalse()
        ->and(json_decode((string)DB::table('finisterre_tasks')->where('id', 1)->value('subtasks'), true))
        ->toBe([
            ['title' => 'First', 'completed' => true],
            ['title' => 'Second', 'completed' => false],
        ]);
});
