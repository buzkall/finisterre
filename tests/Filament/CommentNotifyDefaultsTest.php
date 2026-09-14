<?php

use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Tests\Support\MediaAvatarUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function commentsComponent(FinisterreTask $task)
{
    return Livewire::test(FinisterreCommentsComponent::class, ['record' => $task]);
}

it('preselects the task creator in the notify field', function() {
    $creator = User::factory()->create();
    User::factory()->create();

    $task = FinisterreTask::factory()->create([
        'creator_id'  => $creator->id,
        'assignee_id' => $this->user->id,
    ]);

    commentsComponent($task)->assertSet('data.notify', [$creator->id]);
});

it('preselects nobody when the task creator is the one commenting', function() {
    User::factory()->count(2)->create();

    $task = FinisterreTask::factory()->create([
        'creator_id'  => $this->user->id,
        'assignee_id' => $this->user->id,
    ]);

    commentsComponent($task)->assertSet('data.notify', []);
});

it('still preselects the only other user when there is just one', function() {
    $other = User::factory()->create();

    $task = FinisterreTask::factory()->create([
        'creator_id'  => $this->user->id,
        'assignee_id' => $other->id,
    ]);

    commentsComponent($task)->assertSet('data.notify', [$other->id]);
});

it('loads the avatars of a media library host without a query per commenter', function() {
    // Each comment shows its creator's avatar, and a host keeping avatars in a media
    // library reads a relation for it: without it loaded up front that is the N+1 an
    // application's query detector reports on the task page.
    $task = FinisterreTask::factory()->create([
        'creator_id'  => $this->user->id,
        'assignee_id' => $this->user->id,
    ]);

    foreach (User::factory()->count(3)->create() as $person) {
        foreach (range(1, 2) as $i) {
            FinisterreTaskComment::create([
                'task_id'    => $task->id,
                'comment'    => 'Comment ' . $i,
                'creator_id' => $person->id,
            ]);
        }
    }

    config()->set('finisterre.authenticatable', MediaAvatarUser::class);
    config()->set('finisterre.comments.display_avatars', true);

    $mediaQueries = 0;
    DB::listen(function($query) use (&$mediaQueries) {
        if (str_starts_with($query->sql, 'select * from "media"')) {
            $mediaQueries++;
        }
    });

    commentsComponent($task)->assertOk();

    // One eager load covering every commenter, not one per person.
    expect($mediaQueries)->toBe(1);
});
