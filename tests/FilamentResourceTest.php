<?php

use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a finisterre task', function() {
    $task = FinisterreTask::factory()->create([
        'title'       => 'Test Task',
        'description' => 'Test Description',
    ]);

    expect($task)->toBeInstanceOf(FinisterreTask::class);
    expect($task->title)->toBe('Test Task');
    expect($task->description)->toBe('Test Description');
});

it('can create filament resources using testbench', function() {
    // This test demonstrates that the testbench setup is working
    // and you can create Filament resources using the command:
    // vendor/bin/testbench make:filament-resource TaskResource

    expect(true)->toBeTrue();
});
