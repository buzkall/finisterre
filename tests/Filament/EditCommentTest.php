<?php

use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->task = FinisterreTask::factory()->create(['creator_id' => $this->user->id]);
});

function editComment(object $test, int $commentId, ?FinisterreTask $task = null): mixed
{
    return Livewire::test(FinisterreCommentsComponent::class, ['record' => $task ?? $test->task])
        ->mountAction(TestAction::make('editComment')->arguments(['comment_id' => $commentId]));
}

function stateWithout(string $text): Closure
{
    return fn(array $state): bool => ! str_contains((string)json_encode($state), $text);
}

it('fills the form with the comment of the task being shown', function() {
    $when = now()->addDay()->startOfMinute();
    $comment = $this->task->comments()->create(['comment' => '<p>mine</p>', 'creator_id' => $this->user->id, 'scheduled_for' => $when]);

    editComment($this, $comment->id)
        ->assertActionDataSet(['comment' => '<p>mine</p>']);
});

it('does not fill the form with a comment of another task', function() {
    $otherTask = FinisterreTask::factory()->create(['creator_id' => $this->user->id]);
    $comment = $otherTask->comments()->create(['comment' => '<p>elsewhere</p>', 'creator_id' => $this->user->id]);

    editComment($this, $comment->id)
        ->assertActionDataSet(stateWithout('elsewhere'));
});

it('does not fill the form with the pending scheduled comment of another user', function() {
    $other = User::factory()->create();
    $comment = $this->task->comments()->create([
        'comment'       => '<p>secret plan</p>',
        'creator_id'    => $other->id,
        'scheduled_for' => now()->addDay(),
    ]);

    editComment($this, $comment->id)
        ->assertActionDataSet(stateWithout('secret plan'));
});

it('does not fill the form with a comment the user may not update', function() {
    $other = User::factory()->create();
    $comment = $this->task->comments()->create(['comment' => '<p>theirs</p>', 'creator_id' => $other->id]);
    $sent = $this->task->comments()->create(['comment' => '<p>sent</p>', 'creator_id' => $this->user->id, 'sent_at' => now()]);

    editComment($this, $comment->id)->assertActionDataSet(stateWithout('theirs'));
    editComment($this, $sent->id)->assertActionDataSet(stateWithout('sent'));
});

it('does not save an edit to a comment of another task', function() {
    $otherTask = FinisterreTask::factory()->create(['creator_id' => $this->user->id]);
    $comment = $otherTask->comments()->create(['comment' => '<p>elsewhere</p>', 'creator_id' => $this->user->id, 'scheduled_for' => now()->addDay()]);

    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->callAction(TestAction::make('editComment')->arguments(['comment_id' => $comment->id]), ['comment' => '<p>changed</p>']);

    expect($comment->fresh()->comment)->toBe('<p>elsewhere</p>');
});

it('saves the text and the schedule when the user can schedule comments', function() {
    $comment = $this->task->comments()->create(['comment' => '<p>old</p>', 'creator_id' => $this->user->id, 'scheduled_for' => now()->addDay()]);
    $later = now()->addDays(2)->startOfMinute();

    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->callAction(TestAction::make('editComment')->arguments(['comment_id' => $comment->id]), [
            'comment'       => '<p>new</p>',
            'scheduled_for' => $later->toDateTimeString(),
        ])
        ->assertHasNoActionErrors();

    expect($comment->fresh())
        ->comment->toBe('<p>new</p>')
        ->scheduled_for->toEqual($later);
});

it('leaves the schedule out of the form when the user cannot schedule comments', function() {
    FinisterrePlugin::get()->userCanScheduleComments(false);
    $comment = $this->task->comments()->create(['comment' => '<p>old</p>', 'creator_id' => $this->user->id, 'scheduled_for' => now()->addDay()]);

    editComment($this, $comment->id)
        ->assertActionDataSet(['comment' => '<p>old</p>'])
        ->assertSchemaComponentHidden('scheduled_for', 'mountedActionSchema0');
});

it('saves the text and keeps the schedule when the user cannot schedule comments', function() {
    FinisterrePlugin::get()->userCanScheduleComments(false);
    $when = now()->addDay()->startOfMinute();
    $comment = $this->task->comments()->create(['comment' => '<p>old</p>', 'creator_id' => $this->user->id, 'scheduled_for' => $when]);

    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->callAction(TestAction::make('editComment')->arguments(['comment_id' => $comment->id]), ['comment' => '<p>new</p>'])
        ->assertHasNoActionErrors();

    expect($comment->fresh())
        ->comment->toBe('<p>new</p>')
        ->scheduled_for->toEqual($when);
});
