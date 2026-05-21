<?php

use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Support\AuthenticatableFilter;

it('wraps a scalar filter value in an array', function() {
    config(['finisterre.authenticatable_filter_value' => 'admin']);

    expect(AuthenticatableFilter::values())->toBe(['admin']);
});

it('unwraps a single backed enum filter value to its scalar value', function() {
    config(['finisterre.authenticatable_filter_value' => TaskPriorityEnum::Urgent]);

    expect(AuthenticatableFilter::values())->toBe(['urgent']);
});

it('normalizes an array of mixed strings and backed enums', function() {
    config(['finisterre.authenticatable_filter_value' => ['admin', TaskPriorityEnum::High]]);

    expect(AuthenticatableFilter::values())->toBe(['admin', 'high']);
});

it('unwraps a backed enum and passes scalars through with scalar()', function() {
    expect(AuthenticatableFilter::scalar(TaskPriorityEnum::Low))->toBe('low')
        ->and(AuthenticatableFilter::scalar('admin'))->toBe('admin')
        ->and(AuthenticatableFilter::scalar(5))->toBe(5);
});
