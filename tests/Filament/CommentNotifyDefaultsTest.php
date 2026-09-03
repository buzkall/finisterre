<?php

use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\Models\FinisterreTask;
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
