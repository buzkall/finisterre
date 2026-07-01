<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;

it('lists all task status values', function() {
    expect(TaskStatusEnum::values())->toBe([
        'open', 'doing', 'on_hold', 'to_deploy', 'done', 'rejected', 'backlog',
    ]);
});

it('lists all task priority values', function() {
    expect(TaskPriorityEnum::values())->toBe(['low', 'medium', 'high', 'urgent']);
});

it('returns a color per task status', function() {
    expect(TaskStatusEnum::Open->getColor())->toBe('gray')
        ->and(TaskStatusEnum::Doing->getColor())->toBe('info')
        ->and(TaskStatusEnum::OnHold->getColor())->toBe('warning')
        ->and(TaskStatusEnum::ToDeploy->getColor())->toBe('primary')
        ->and(TaskStatusEnum::Done->getColor())->toBe('success')
        ->and(TaskStatusEnum::Rejected->getColor())->toBe('danger')
        ->and(TaskStatusEnum::Backlog->getColor())->toBe('gray');
});

it('returns a color per task priority', function() {
    expect(TaskPriorityEnum::Low->getColor())->toBe('gray')
        ->and(TaskPriorityEnum::Medium->getColor())->toBe('success')
        ->and(TaskPriorityEnum::High->getColor())->toBe('info')
        ->and(TaskPriorityEnum::Urgent->getColor())->toBe('danger');
});

it('returns a tailwind background per task priority', function() {
    expect(TaskPriorityEnum::Low->color())->toBe('bg-gray-200')
        ->and(TaskPriorityEnum::Medium->color())->toBe('bg-green-300')
        ->and(TaskPriorityEnum::High->color())->toBe('bg-blue-300')
        ->and(TaskPriorityEnum::Urgent->color())->toBe('bg-red-300');
});

it('filters task status cases according to config', function() {
    config(['finisterre.hidden_statuses' => ['backlog', 'rejected']]);

    $values = TaskStatusEnum::filteredCases()->map(fn($c) => $c->value)->all();

    expect($values)->not->toContain('backlog')
        ->and($values)->not->toContain('rejected')
        ->and($values)->toContain('open');
});

it('returns no hidden statuses by default', function() {
    config(['finisterre.hidden_statuses' => []]);

    expect(TaskStatusEnum::filteredCases()->count())->toBe(count(TaskStatusEnum::cases()));
});

it('returns options keyed by value', function() {
    config(['finisterre.hidden_statuses' => []]);

    $options = TaskStatusEnum::options();

    expect($options)->toHaveKey('open')
        ->and($options)->toHaveKey('done')
        ->and($options['open'])->not->toBeNull();
});

it('returns a label via translations', function() {
    expect(TaskStatusEnum::Open->getLabel())->toBeString()
        ->and(TaskPriorityEnum::Urgent->getLabel())->toBeString();
});

it('exposes getTitle as alias of getLabel for status', function() {
    foreach (TaskStatusEnum::cases() as $case) {
        expect($case->getTitle())->toBe($case->getLabel());
    }
});
