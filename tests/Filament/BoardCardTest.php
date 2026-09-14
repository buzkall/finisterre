<?php

use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Tests\Support\AvatarUser;
use Arzcode\Finisterre\Tests\Support\MediaAvatarUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    // Only the lookups the cards make; the board query mentions the users table
    // too, in the name subselects.
    $userLookups = 0;
    DB::listen(function($query) use (&$userLookups) {
        if (preg_match('/^select \* from .users. where .users.\..id. (=|in) /', $query->sql)) {
            $userLookups++;
        }
    });

    Model::preventLazyLoading();

    try {
        Livewire::test(TasksKanbanBoard::class)->assertOk();
    } finally {
        Model::preventLazyLoading(false);
    }

    // Everyone on the board in one query, not one per person and not one per card.
    expect($userLookups)->toBe(1);
});

it('loads the avatars of a media library host without a query per user', function() {
    // The common Filament host keeps the avatar in a media library, so reading
    // it touches a relation: without it loaded up front that is the N+1 an
    // application's query detector reports on the board.
    $people = User::factory()->count(3)->create();

    foreach (range(1, 9) as $i) {
        FinisterreTask::factory()->create([
            'title'       => 'Task ' . $i,
            'status'      => TaskStatusEnum::Open,
            'archived'    => false,
            'creator_id'  => $people[$i % 3]->id,
            'assignee_id' => $people[($i + 1) % 3]->id,
        ]);
    }

    config()->set('finisterre.authenticatable', MediaAvatarUser::class);

    // Only the standalone reads of the media table: the board query mentions it
    // too, in the subselect that counts a card's attachments.
    $mediaQueries = 0;
    DB::listen(function($query) use (&$mediaQueries) {
        if (str_starts_with($query->sql, 'select * from "media"')) {
            $mediaQueries++;
        }
    });

    Model::preventLazyLoading();

    try {
        Livewire::test(TasksKanbanBoard::class)->assertOk();
    } finally {
        Model::preventLazyLoading(false);
    }

    // One eager load covering every user on the board, not one per person. No card
    // here has a card image, so the cover relation asks for nothing at all.
    expect($mediaQueries)->toBe(1);
});

it('loads the card images of a whole board in a single query', function() {
    Storage::fake('public');

    $user = User::factory()->create(['name' => 'Ana Ruiz']);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    foreach (range(1, 8) as $i) {
        $task = FinisterreTask::factory()->create([
            'title'       => 'Task ' . $i,
            'status'      => TaskStatusEnum::Open,
            'archived'    => false,
            'creator_id'  => $user->id,
            'assignee_id' => $user->id,
        ]);

        // The observer promotes it to the card image, so every card has one.
        $task->addMediaFromString($png)->usingFileName('shot.png')->toMediaCollection('tasks', 'public');
    }

    $mediaQueries = 0;
    DB::listen(function($query) use (&$mediaQueries) {
        if (str_starts_with($query->sql, 'select * from "media"')) {
            $mediaQueries++;
        }
    });

    Model::preventLazyLoading();

    try {
        Livewire::test(TasksKanbanBoard::class)->assertOk();
    } finally {
        Model::preventLazyLoading(false);
    }

    // The host here keeps no avatars in a media library, so this one read is the
    // covers: eight cards, one query, not one per card.
    expect($mediaQueries)->toBe(1);
});

it('shows the card image at the top of a card that has one', function() {
    Storage::fake('public');

    $user = User::factory()->create(['name' => 'Ana Ruiz']);

    $task = FinisterreTask::factory()->create([
        'title'       => 'With a picture',
        'status'      => TaskStatusEnum::Open,
        'archived'    => false,
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
    ]);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $media = $task->addMediaFromString($png)
        ->usingFileName('shot.png')
        ->toMediaCollection('tasks', 'public');

    Livewire::test(TasksKanbanBoard::class)
        ->assertOk()
        ->assertSee($media->getUrl(), escape: false);
});

it('crops the card image at the position the user dragged it to', function() {
    Storage::fake('public');

    $user = User::factory()->create(['name' => 'Ana Ruiz']);

    $task = FinisterreTask::factory()->create([
        'title'       => 'Repositioned',
        'status'      => TaskStatusEnum::Open,
        'archived'    => false,
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
    ]);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $task->addMediaFromString($png)
        ->usingFileName('shot.png')
        ->withCustomProperties([FinisterreTask::COVER_POSITION_PROPERTY => 30])
        ->toMediaCollection('tasks', 'public');

    Livewire::test(TasksKanbanBoard::class)
        ->assertOk()
        ->assertSee('object-position: 50% 30%', escape: false);
});

it('renders a card without a picture when the task has no card image', function() {
    $user = User::factory()->create(['name' => 'Ana Ruiz']);

    FinisterreTask::factory()->create([
        'title'       => 'No picture',
        'status'      => TaskStatusEnum::Open,
        'archived'    => false,
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
    ]);

    Livewire::test(TasksKanbanBoard::class)
        ->assertOk()
        ->assertSee('No picture')
        ->assertDontSee('object-cover"', escape: false);
});
