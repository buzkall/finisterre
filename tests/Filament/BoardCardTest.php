<?php

use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Tests\Support\AvatarUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    // Filament's Authenticate middleware 403s a user model that does not
    // implement FilamentUser unless the app runs locally.
    config()->set('app.env', 'local');

    $this->actingAs(User::factory()->create(['name' => 'Ana Ruiz']));
});

it('stacks the creator behind the assignee when they are different people', function() {
    $creator = User::factory()->create(['name' => 'Marc Gil']);
    $assignee = User::factory()->create(['name' => 'Berta Lopez']);

    FinisterreTask::factory()->create([
        'title'       => 'Reported by somebody else',
        'status'      => TaskStatusEnum::Open,
        'archived'    => false,
        'creator_id'  => $creator->id,
        'assignee_id' => $assignee->id,
    ]);

    Livewire::test(TasksKanbanBoard::class)
        ->assertSee(__('finisterre::finisterre.creator_name') . ': Marc Gil')
        ->assertSee(__('finisterre::finisterre.assignee_name') . ': Berta Lopez')
        ->assertSee('MG')
        ->assertSee('BL');
});

it('shows a single avatar when the creator is the assignee', function() {
    $user = User::factory()->create(['name' => 'Marc Gil']);

    FinisterreTask::factory()->create([
        'title'       => 'Self assigned',
        'status'      => TaskStatusEnum::Open,
        'archived'    => false,
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
    ]);

    Livewire::test(TasksKanbanBoard::class)
        ->assertDontSee(__('finisterre::finisterre.creator_name') . ': Marc Gil')
        ->assertSee(__('finisterre::finisterre.assignee_name') . ': Marc Gil');
});

it('still shows the creator on a task nobody is assigned to', function() {
    $creator = User::factory()->create(['name' => 'Marc Gil']);

    $task = FinisterreTask::factory()->create([
        'title'      => 'Unassigned',
        'status'     => TaskStatusEnum::Open,
        'archived'   => false,
        'creator_id' => $creator->id,
    ]);

    // The observer fills the fallback assignee on create, so clear it after.
    $task->updateQuietly(['assignee_id' => null]);

    Livewire::test(TasksKanbanBoard::class)
        ->assertSee(__('finisterre::finisterre.creator_name') . ': Marc Gil')
        ->assertSee('MG');
});

it('shows the host application avatar instead of the initials when there is one', function() {
    $creator = User::factory()->create(['name' => 'Marc Gil']);
    $assignee = User::factory()->create(['name' => 'Berta Lopez']);

    FinisterreTask::factory()->create([
        'title'       => 'With avatars',
        'status'      => TaskStatusEnum::Open,
        'archived'    => false,
        'creator_id'  => $creator->id,
        'assignee_id' => $assignee->id,
    ]);

    config()->set('finisterre.authenticatable', AvatarUser::class);

    Livewire::test(TasksKanbanBoard::class)
        ->assertSee('/storage/avatars/' . $assignee->id . '.jpg')
        ->assertSee('/storage/avatars/' . $creator->id . '.jpg')
        ->assertDontSee('>BL<', escape: false)
        ->assertDontSee('>MG<', escape: false);
});

it('falls back to the initials when the host has no avatar for the user', function() {
    $assignee = User::factory()->create(['name' => 'Sin Foto']);

    FinisterreTask::factory()->create([
        'title'       => 'No avatar',
        'status'      => TaskStatusEnum::Open,
        'archived'    => false,
        'creator_id'  => $assignee->id,
        'assignee_id' => $assignee->id,
    ]);

    config()->set('finisterre.authenticatable', AvatarUser::class);

    Livewire::test(TasksKanbanBoard::class)
        ->assertDontSee('/storage/avatars/')
        ->assertSee('SF');
});

it('renders every card without lazy loading a relation or querying a user per card', function() {
    // The board is the one page that renders many records at once, so a relation
    // touched inside the card view is an N+1 the host's detector will flag.
    $people = User::factory()->count(3)->create();

    foreach (range(1, 12) as $i) {
        FinisterreTask::factory()->create([
            'title'       => 'Task ' . $i,
            'status'      => TaskStatusEnum::Open,
            'archived'    => false,
            'creator_id'  => $people[$i % 3]->id,
            'assignee_id' => $people[($i + 1) % 3]->id,
        ]);
    }

    config()->set('finisterre.authenticatable', AvatarUser::class);

    // Only the per-user lookups the cards make; the board query mentions the
    // users table too, in the name subselects.
    $userLookups = 0;
    DB::listen(function($query) use (&$userLookups) {
        if (preg_match('/^select \* from .users. where .users.\..id. = /', $query->sql)) {
            $userLookups++;
        }
    });

    Model::preventLazyLoading();

    try {
        Livewire::test(TasksKanbanBoard::class)->assertOk();
    } finally {
        Model::preventLazyLoading(false);
    }

    // One lookup per distinct person, not one per card.
    expect($userLookups)->toBeLessThanOrEqual(count($people));
});
