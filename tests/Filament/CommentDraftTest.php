<?php

use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\Models\FinisterreTask;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->task = FinisterreTask::factory()->create(['creator_id' => $this->user->id]);
});

it('keeps the draft of each user and task apart in the browser', function() {
    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->assertSeeHtml('finisterre-comment-draft:' . $this->user->id . ':' . $this->task->id);
});

it('tells the browser to drop the draft once the comment is saved', function() {
    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->set('data.comment', '<p>Saved</p>')
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertDispatched('finisterre-comment-saved', taskId: $this->task->id);

    expect($this->task->comments()->count())->toBe(1);
});

it('keeps the draft when the comment is not saved', function() {
    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->set('data.comment', '')
        ->call('create')
        ->assertNotDispatched('finisterre-comment-saved');

    expect($this->task->comments()->count())->toBe(0);
});
