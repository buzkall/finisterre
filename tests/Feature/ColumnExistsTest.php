<?php

use Arzcode\Finisterre\Support\ColumnExists;
use Illuminate\Support\Facades\DB;

/**
 * @param  Closure(): mixed  $callback
 */
function queriesRunBy(Closure $callback): int
{
    $queries = 0;
    DB::listen(function() use (&$queries) {
        $queries++;
    });

    $callback();

    return $queries;
}

it('asks the database once per column in a request', function() {
    $first = queriesRunBy(fn() => ColumnExists::in('users', 'name'));

    expect($first)->toBeGreaterThan(0)
        ->and(ColumnExists::in('users', 'name'))->toBeTrue()
        ->and(queriesRunBy(fn() => ColumnExists::in('users', 'name')))->toBe(0)
        ->and(ColumnExists::in('users', 'nope'))->toBeFalse()
        ->and(queriesRunBy(fn() => ColumnExists::in('users', 'nope')))->toBe(0);
});

it('starts every request without the answers of the last one', function() {
    ColumnExists::in('users', 'name');

    // What Octane and the queue worker do between requests and jobs.
    app()->forgetScopedInstances();

    expect(queriesRunBy(fn() => ColumnExists::in('users', 'name')))->toBeGreaterThan(0);
});

it('forgets the answers on request, for code that changes the schema', function() {
    ColumnExists::in('users', 'name');
    ColumnExists::forget();

    expect(queriesRunBy(fn() => ColumnExists::in('users', 'name')))->toBeGreaterThan(0);
});
