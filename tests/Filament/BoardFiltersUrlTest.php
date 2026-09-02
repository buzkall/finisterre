<?php

use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Models\FinisterreTask;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    // Filament's Authenticate middleware 403s a user model that does not
    // implement FilamentUser unless the app runs locally.
    config()->set('app.env', 'local');

    $this->actingAs(User::factory()->create());
});

it('renders the board when the filters come back in the query string', function() {
    // Going back to the board (cmd + left arrow from a task) reloads it with the
    // filters Livewire had written to the URL. The board page must not name that
    // property `filters`: Filament forwards a page property with that name to
    // every widget as a `pageFilters` mount param, which ends up as an HTML
    // attribute of the lazy widget's placeholder and throws
    // "trim(): Argument #1 ($string) must be of type string, array given".
    $this->withoutExceptionHandling()
        ->get('/admin/tasks?taskFilters%5Bfilter_show_archived%5D=false')
        ->assertOk();
});

it('keeps archived tasks hidden when the toggle comes back as the string "false"', function() {
    $archived = FinisterreTask::factory()->create([
        'title'    => 'Archived task',
        'status'   => TaskStatusEnum::Open,
        'archived' => true,
    ]);

    Livewire::withQueryParams(['taskFilters' => ['filter_show_archived' => 'false']])
        ->test(TasksKanbanBoard::class)
        ->assertDontSee($archived->title);
});
