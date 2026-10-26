<?php

use Arzcode\Finisterre\Support\ColumnExists;
use Arzcode\Finisterre\Support\TaskFollowers;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

afterEach(fn() => TaskFollowers::forgetTable());

it('asks about the schema again once migrations have run, whoever ran them', function() {
    ColumnExists::in('users', 'name');

    Schema::rename('finisterre_task_followers', 'finisterre_task_followers_away');
    TaskFollowers::forgetTable();
    expect(TaskFollowers::available())->toBeFalse();
    Schema::rename('finisterre_task_followers_away', 'finisterre_task_followers');

    event(new MigrationsEnded('up'));

    $queries = 0;
    DB::listen(function() use (&$queries) {
        $queries++;
    });

    expect(ColumnExists::in('users', 'name'))->toBeTrue()
        ->and($queries)->toBeGreaterThan(0)
        ->and(TaskFollowers::available())->toBeTrue();
});
