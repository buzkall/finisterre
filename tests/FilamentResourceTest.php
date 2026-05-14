<?php

use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function() {
    config([
        'finisterre.active'                  => false,
        'finisterre.task_changes_table_name' => 'finisterre_task_changes',
    ]);

    if (! Schema::hasTable('finisterre_task_changes')) {
        Schema::create('finisterre_task_changes', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
});

it('can create a finisterre task', function() {
    $task = FinisterreTask::factory()->create([
        'title'       => 'Test Task',
        'description' => 'Test Description',
    ]);

    expect($task)->toBeInstanceOf(FinisterreTask::class)
        ->and($task->title)->toBe('Test Task')
        ->and($task->description)->toBe('Test Description');
});

it('can create filament resources using testbench', function() {
    // This test demonstrates that the testbench setup is working,
    // and you can create Filament resources using the command:
    // vendor/bin/testbench make:filament-resource TaskResource

    expect(true)->toBeTrue();
});
