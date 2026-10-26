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

it('strips scripts and event handlers from the comments it shows', function() {
    $this->task->comments()->create([
        'creator_id' => $this->user->id,
        'comment'    => '<p onclick="steal()">hello</p><script>alert(1)</script><img src="/storage/finisterre-files/a.png" onerror="alert(2)"><a href="javascript:alert(3)">link</a>',
    ]);

    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->assertSeeHtml('hello')
        ->assertSeeHtml('/storage/finisterre-files/a.png')
        ->assertDontSeeHtml('<script')
        ->assertDontSeeHtml('alert(1)')
        ->assertDontSeeHtml('onerror')
        ->assertDontSeeHtml('onclick')
        ->assertDontSeeHtml('javascript:');
});

it('still turns the links of a comment into anchors', function() {
    $this->task->comments()->create([
        'creator_id' => $this->user->id,
        'comment'    => '<p>see https://example.test/page?a=1&b=2 now</p>',
    ]);

    Livewire::test(FinisterreCommentsComponent::class, ['record' => $this->task])
        ->assertSeeHtml('<a href="https://example.test/page?a&#61;1&amp;b&#61;2" target="_blank"');
});
