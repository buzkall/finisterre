<?php

use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Filament\Widgets\FilterTasksWidget;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    // Filament's Authenticate middleware 403s a user model that does not
    // implement FilamentUser unless the app runs locally.
    config()->set('app.env', 'local');

    $this->actingAs(User::factory()->create());
});

it('folds the filter panel away below the lg breakpoint', function() {
    Livewire::test(FilterTasksWidget::class)
        // Open on a desktop, folded on a phone, and reopened when a tablet is
        // turned to landscape.
        ->assertSeeHtml("open: window.matchMedia('(min-width: 1024px)').matches")
        ->assertSeeHtml('x-show="open"')
        // The button that folds it lives in the page header, not in the widget.
        ->assertSeeHtml('x-on:finisterre-toggle-filters.window="open = ! open"');
});

it('takes the empty widget row out of the page while the panel is folded', function() {
    // An empty widget still holds a row in the page content, and the gap on
    // either side of it doubles the space between the header and the board.
    Livewire::test(FilterTasksWidget::class)
        ->assertSeeHtml('x-bind:data-finisterre-filters-folded="open ? null : true"')
        ->assertSeeHtml('.fi-grid:has([data-finisterre-filters-folded])');
});

it('puts the filters button in the page header, next to the other actions', function() {
    $board = Livewire::test(TasksKanbanBoard::class);

    $button = $board->instance()->getAction('toggleFilters');

    expect($button->getLabel())->toBe(__('finisterre::finisterre.filter.label'))
        // Folding the panel is done in the browser: no round trip to the server.
        ->and($button->getAlpineClickHandler())->toBe("\$dispatch('finisterre-toggle-filters')")
        ->and($button->getLivewireClickHandler())->toBeNull()
        // And only on the screens the panel is folded on.
        ->and($button->getExtraAttributes()['class'])->toContain('lg:hidden');
});

it('counts the filters hidden behind the button', function() {
    $board = Livewire::test(TasksKanbanBoard::class);

    expect($board->instance()->activeFilterCount())->toBe(0);

    $board->call('updateFilters', [
        'filter_text'          => 'boiler',
        'filter_tags'          => [1],
        'filter_assignee'      => 1,
        'filter_show_archived' => true,
    ]);

    expect($board->instance()->activeFilterCount())->toBe(4);
});

it('does not count the show archived toggle when it comes back as the string "false"', function() {
    // The same string the board query has to guard against: truthy in PHP, but
    // it means the archived tasks are hidden, which is no filter at all.
    $board = Livewire::withQueryParams([
        'taskFilters' => ['filter_show_archived' => 'false'],
    ])->test(TasksKanbanBoard::class);

    expect($board->instance()->activeFilterCount())->toBe(0);
});
