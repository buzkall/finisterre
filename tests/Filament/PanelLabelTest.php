<?php

use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Support\PanelLabel;

it('names tasks with the translated default when no label is configured', function() {
    expect(PanelLabel::singular())->toBe(__('finisterre::finisterre.task'))
        ->and(PanelLabel::plural())->toBe(__('finisterre::finisterre.tasks'))
        ->and(FinisterreTaskResource::getModelLabel())->toBe(__('finisterre::finisterre.task'))
        ->and(TasksKanbanBoard::getNavigationLabel())->toBe(__('finisterre::finisterre.tasks'));
});

it('renames tasks everywhere in the panel from the config', function() {
    config()->set([
        'finisterre.label'        => 'Ticket',
        'finisterre.plural_label' => 'Tickets',
    ]);

    expect(FinisterreTaskResource::getModelLabel())->toBe('Ticket')
        ->and(FinisterreTaskResource::getPluralLabel())->toBe('Tickets')
        ->and(TasksKanbanBoard::getNavigationLabel())->toBe('Tickets');
});

it('translates a configured label that is a translation key', function() {
    config()->set('finisterre.plural_label', 'finisterre::finisterre.create_task');

    expect(PanelLabel::plural())->toBe(__('finisterre::finisterre.create_task'));
});

it('falls back to the translated default when the configured label is empty', function() {
    config()->set([
        'finisterre.label'        => '',
        'finisterre.plural_label' => null,
    ]);

    expect(PanelLabel::singular())->toBe(__('finisterre::finisterre.task'))
        ->and(PanelLabel::plural())->toBe(__('finisterre::finisterre.tasks'));
});
