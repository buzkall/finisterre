<?php

use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\User;

it('gives the images already in descriptions and comments to every task that loads them', function() {
    $migration = include __DIR__ . '/../../database/migrations/add_editor_files_to_finisterre_tasks.php.stub';
    $migration->down();

    $user = User::factory()->create();

    [$first, $second, $third] = FinisterreTask::withoutEvents(fn() => [
        FinisterreTask::factory()->create([
            'creator_id'  => $user->id,
            'description' => '<img src="/storage/finisterre-files/shared.png"><img src="/storage/finisterre-files/shared.png">',
        ]),
        FinisterreTask::factory()->create([
            'creator_id'  => $user->id,
            'description' => '<img src="/storage/public-image.png">',
        ]),
        FinisterreTask::factory()->create([
            'creator_id'  => $user->id,
            'description' => '<p>No images</p>',
        ]),
    ]);

    DB::table('finisterre_task_comments')->insert([
        ['task_id' => $second->id, 'creator_id' => $user->id, 'comment' => '<img src="https://app.test/storage/finisterre-files/shared.png">', 'created_at' => now(), 'updated_at' => now()],
        ['task_id' => $second->id, 'creator_id' => $user->id, 'comment' => '<img src="/storage/finisterre-files/in-comment.png?v=2">', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->up();

    expect($first->refresh()->editor_files)->toBe(['shared.png'])
        ->and($second->refresh()->editor_files)->toBe(['shared.png', 'in-comment.png'])
        ->and($third->refresh()->editor_files)->toBeNull();
});
