<?php

use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ViewFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas\TagsSelect;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;

beforeEach(function() {
    config([
        'finisterre.active'                  => false,
        'finisterre.table_name'              => 'finisterre_tasks',
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

    if (! Schema::hasTable('tags')) {
        Schema::create('tags', function(Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->string('type')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });

        Schema::create('taggables', function(Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
        });
    }

    $this->actingAs(User::factory()->create());
});

it('registers the task page as the record route', function() {
    $pages = FinisterreTaskResource::getPages();

    expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit'])
        ->and($pages['view']->getPage())->toBe(ViewFinisterreTask::class);
});

it('syncs tags and touches the task without notifying anybody', function() {
    Notification::fake();

    $task = FinisterreTask::factory()->create();
    $task->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $tag = FinisterreTag::findOrCreateFromString('Backend', 'tasks');

    TagsSelect::persist($task, [$tag->getKey()]);

    expect($task->refresh()->tags->pluck('id')->all())->toBe([$tag->getKey()])
        ->and($task->updated_at->isToday())->toBeTrue();

    TagsSelect::persist($task, null);

    expect($task->refresh()->tags)->toBeEmpty();

    Notification::assertNothingSent();
});
