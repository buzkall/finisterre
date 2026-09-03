<?php

use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;

it('excludes the task resource from global search by default', function() {
    expect(FinisterreTaskResource::canGloballySearch())->toBeFalse();
});

it('lets the task resource be globally searchable when the config opts in', function() {
    config()->set('finisterre.exclude_from_global_search', false);

    expect(FinisterreTaskResource::canGloballySearch())->toBeTrue();
});

it('keeps the task resource out of global search while finisterre is inactive', function() {
    config()->set([
        'finisterre.active'                     => false,
        'finisterre.exclude_from_global_search' => false,
    ]);

    expect(FinisterreTaskResource::canGloballySearch())->toBeFalse();
});
