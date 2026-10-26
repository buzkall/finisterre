<?php

use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\User;

beforeEach(function() {
    config()->set('app.env', 'local');

    $this->actingAs(User::factory()->create());
});

it('answers 404 to a segment that is not a number on the view and edit routes', function(string $page) {
    $this->get(FinisterreTaskResource::getUrl($page, ['record' => 'settings'], isAbsolute: false))
        ->assertNotFound();
})->with(['view', 'edit']);

it('does not query for a key that cannot exist', function(string $key) {
    $queries = [];
    DB::listen(function($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    expect(FinisterreTaskResource::resolveRecordRouteBinding($key))->toBeNull()
        ->and($queries)->toBe([]);
})->with(['settings', '1abc', '-1', '1.5', '', '9223372036854775808', '99999999999999999999']);

it('still finds a task by its numeric key', function() {
    $task = FinisterreTask::factory()->create();

    expect(FinisterreTaskResource::resolveRecordRouteBinding((string)$task->getKey())?->is($task))->toBeTrue()
        ->and(FinisterreTaskResource::resolveRecordRouteBinding($task->getKey())?->is($task))->toBeTrue();
});

it('answers 200 for the view route of an existing task', function() {
    $task = FinisterreTask::factory()->create();

    $this->get(FinisterreTaskResource::getUrl('view', ['record' => $task], isAbsolute: false))->assertOk();
});
