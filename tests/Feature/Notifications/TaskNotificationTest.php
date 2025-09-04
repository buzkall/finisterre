<?php

use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Notifications\TaskNotification;
use Mockery;

it('can generate task edit URL without Filament routing errors', function() {
    // Create a mock task
    $task = Mockery::mock(FinisterreTask::class);
    $task->shouldReceive('getAttribute')->with('id')->andReturn(1);

    // Create notification instance
    $notification = new TaskNotification($task);

    // This should not throw the generateRouteName error
    $url = $notification->getTaskEditUrl();

    expect($url)->toBeString();
    expect($url)->toContain('/1/edit');
});

it('falls back to simple URL when Filament getUrl fails', function() {
    // Create a mock task
    $task = Mockery::mock(FinisterreTask::class);
    $task->shouldReceive('getAttribute')->with('id')->andReturn(1);

    // Create notification instance
    $notification = new TaskNotification($task);

    // This should work and generate a URL
    $url = $notification->getTaskEditUrl();

    expect($url)->toBeString();
    expect($url)->toContain('/1/edit');
    // The URL should contain the configured slug
    expect($url)->toContain(config('finisterre.slug') . '/1/edit');
});
