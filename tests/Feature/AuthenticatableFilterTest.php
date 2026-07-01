<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Support\AuthenticatableFilter;

it('wraps a scalar filter value in an array', function() {
    config(['finisterre.authenticatable_filter_value' => 'admin']);

    expect(AuthenticatableFilter::values())->toBe(['admin']);
});

it('splits a comma-separated filter value string into individual values', function() {
    config(['finisterre.authenticatable_filter_value' => 'super_admin,admin_l1']);

    expect(AuthenticatableFilter::values())->toBe(['super_admin', 'admin_l1']);
});

it('trims whitespace and drops empty entries when splitting a string', function() {
    config(['finisterre.authenticatable_filter_value' => ' super_admin , , admin_l1 ']);

    expect(AuthenticatableFilter::values())->toBe(['super_admin', 'admin_l1']);
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
