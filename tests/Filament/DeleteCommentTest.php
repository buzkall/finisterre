<?php

use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Filament\Actions\Testing\TestAction;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->task = FinisterreTask::factory()->create(['creator_id' => $this->user->id]);
});

it('asks for confirmation before deleting a comment', function() {
    $comment = $this->task->comments()->create(['comment' => 'mine', 'creator_id' => $this->user->id]);

    $component = Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->mountAction(TestAction::make('deleteComment')->arguments(['comment_id' => $comment->id]))
        ->assertActionMounted('deleteComment')
        ->assertMountedActionModalSee(__('finisterre::finisterre.comments.delete_confirmation'));

    expect(FinisterreTaskComment::whereKey($comment->id)->exists())->toBeTrue();

    $component->callMountedAction()->assertNotified(__('finisterre::finisterre.comments.notifications.deleted'));

    expect(FinisterreTaskComment::whereKey($comment->id)->exists())->toBeFalse();
});

it('keeps the comments of somebody else', function() {
    $other = User::factory()->create();
    $comment = $this->task->comments()->create(['comment' => 'theirs', 'creator_id' => $other->id]);

    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->callAction(TestAction::make('deleteComment')->arguments(['comment_id' => $comment->id]));

    expect(FinisterreTaskComment::whereKey($comment->id)->exists())->toBeTrue();
});

it('keeps the comments of another task, even ones the user may delete', function() {
    $otherTask = FinisterreTask::factory()->create(['creator_id' => $this->user->id]);
    $comment = $otherTask->comments()->create(['comment' => 'mine elsewhere', 'creator_id' => $this->user->id]);

    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->callAction(TestAction::make('deleteComment')->arguments(['comment_id' => $comment->id]))
        ->assertNotNotified(__('finisterre::finisterre.comments.notifications.deleted'));

    expect(FinisterreTaskComment::whereKey($comment->id)->exists())->toBeTrue();
});

it('no longer deletes a comment without the action', function() {
    $comment = $this->task->comments()->create(['comment' => 'mine', 'creator_id' => $this->user->id]);

    expect(fn() => Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])->call('delete', $comment->id))
        ->toThrow(MethodNotFoundException::class);

    expect(FinisterreTaskComment::whereKey($comment->id)->exists())->toBeTrue();
});
