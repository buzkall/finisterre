<?php

use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

afterEach(function() {
    Model::preventAccessingMissingAttributes(false);
    Model::preventLazyLoading(false);
});

it('exposes attachments without throwing when the host enables strict mode', function() {
    $task = new FinisterreTask;

    // Mimic an app calling Model::shouldBeStrict(): Filament internally reads
    // data_get($record, 'attachments') for the media field, and the media
    // relation is not eager-loaded.
    Model::preventAccessingMissingAttributes();
    Model::preventLazyLoading();

    expect(fn() => $task->attachments)->not->toThrow(Exception::class)
        ->and($task->attachments)->toBeInstanceOf(Collection::class)
        ->and($task->attachments)->toBeEmpty();
});
