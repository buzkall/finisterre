<?php

use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Workbench\App\Models\User;

beforeEach(function() {
    config()->set('app.env', 'local');

    $this->actingAs(User::factory()->create());
});

function boardNavigationItem(): NavigationItem
{
    return collect(Filament::getNavigation())
        ->flatMap(fn($group) => $group->getItems())
        ->firstOrFail(fn(NavigationItem $item) => $item->getUrl() === TasksKanbanBoard::getUrl());
}

it('highlights the board navigation item on the task pages', function(string $page) {
    $task = FinisterreTask::factory()->create();

    $url = $page === 'create'
        ? FinisterreTaskResource::getUrl('create')
        : FinisterreTaskResource::getUrl($page, ['record' => $task]);

    $this->get($url)->assertOk();

    expect(boardNavigationItem()->isActive())->toBeTrue();
})->with(['view', 'edit', 'create']);

it('highlights the board navigation item on the board', function() {
    $this->get(TasksKanbanBoard::getUrl())->assertOk();

    expect(boardNavigationItem()->isActive())->toBeTrue();
});
