<?php

use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\Models\FinisterreTask;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('places a scheduled comment in the timeline by its scheduled time', function() {
    $replier = User::factory()->create();
    $task = FinisterreTask::factory()->create(['creator_id' => $this->user->id]);

    $this->travelTo(now()->subHours(3));
    $first = $task->comments()->create(['comment' => 'first', 'creator_id' => $this->user->id]);
    // Written three hours ago but published one hour ago, after the reply below.
    $scheduled = $task->comments()->create([
        'comment'       => 'scheduled',
        'creator_id'    => $this->user->id,
        'scheduled_for' => now()->addHours(2),
        'sent_at'       => now()->addHours(2),
    ]);
    $this->travelBack();

    $this->travelTo(now()->subHours(2));
    $reply = $task->comments()->create(['comment' => 'reply', 'creator_id' => $replier->id]);
    $this->travelBack();

    $ids = Livewire::test(FinisterreCommentsComponent::class, ['record' => $task])
        ->instance()
        ->comments()
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$scheduled->id, $reply->id, $first->id]);
});
