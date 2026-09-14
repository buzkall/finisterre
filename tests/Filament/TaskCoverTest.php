<?php

use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workbench\App\Models\User;

// The Filament suite boots the real service provider, so the media observer that
// picks a task's card image is registered the way a host application registers it.

beforeEach(function() {
    Storage::fake('public');

    $this->actingAs(User::factory()->create());
});

function coverTask(): FinisterreTask
{
    $user = User::factory()->create();

    return FinisterreTask::factory()->create([
        'creator_id'  => $user->id,
        'assignee_id' => $user->id,
    ]);
}

function attachImage(FinisterreTask $task, string $fileName = 'shot.png'): Media
{
    // A real one-pixel PNG, so the card conversion has something it can open.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    return $task->addMediaFromString($png)
        ->usingFileName($fileName)
        ->toMediaCollection('tasks', 'public');
}

it('makes the first image attached to a task its card image', function() {
    $task = coverTask();

    $media = attachImage($task);

    expect($task->refresh()->cover_media_id)->toBe($media->getKey());
});

it('still picks and clears the card image when the host maps tasks to a morph alias', function() {
    // Media library then stores the alias in model_type instead of the class name.
    Relation::morphMap(['finisterre-task' => FinisterreTask::class]);

    try {
        $task = coverTask();
        $media = attachImage($task);

        expect($media->model_type)->toBe('finisterre-task')
            ->and($task->refresh()->cover_media_id)->toBe($media->getKey());

        $media->delete();

        expect($task->refresh()->cover_media_id)->toBeNull();
    } finally {
        // The morph map is static: leaving it set would leak into every later test.
        Relation::morphMap([], false);
    }
});

it('picks the card image while the current panel does not carry the plugin', function() {
    // A host saving media from a panel without Finisterre boots the task model there,
    // through the observer, and resolving the plugin at boot used to throw.
    $website = Panel::make()->id('website')->path('website');
    Filament::registerPanel($website);
    Filament::setCurrentPanel($website);

    $task = coverTask();
    $media = attachImage($task);

    expect($task->refresh()->cover_media_id)->toBe($media->getKey());
});

it('leaves the card image alone once a task already has one', function() {
    $task = coverTask();
    $first = attachImage($task, 'first.png');

    attachImage($task, 'second.png');

    expect($task->refresh()->cover_media_id)->toBe($first->getKey());
});

it('does not put a card image back on a task the user cleared it from', function() {
    $task = coverTask();
    attachImage($task, 'first.png');

    $task->update(['cover_media_id' => null]);

    attachImage($task, 'second.png');

    expect($task->refresh()->cover_media_id)->toBeNull();
});

it('ignores an attachment that is not an image', function() {
    $task = coverTask();

    $task->addMediaFromString('not a picture')
        ->usingFileName('notes.txt')
        ->toMediaCollection('tasks', 'public');

    expect($task->refresh()->cover_media_id)->toBeNull();
});

it('clears the card image when the attachment behind it is deleted', function() {
    $task = coverTask();
    $media = attachImage($task);

    expect($task->refresh()->cover_media_id)->toBe($media->getKey());

    $media->delete();

    expect($task->refresh()->cover_media_id)->toBeNull();
});

it('serves the original while the card thumbnail has not been generated', function() {
    $task = coverTask();
    $media = attachImage($task);

    // The conversion is deferred, so nothing is generated during this request.
    expect($media->refresh()->hasGeneratedConversion(FinisterreTask::COVER_CONVERSION))->toBeFalse()
        ->and($task->refresh()->coverUrl())->toBe($media->getUrl());
});

it('serves the card thumbnail on request and the original for wide spots', function() {
    $task = coverTask();
    $media = attachImage($task);

    // Stand in for the deferred conversion having run after the response.
    $media->markAsConversionGenerated(FinisterreTask::COVER_CONVERSION);
    $media->save();

    $task->refresh();

    expect($task->coverUrl())->toBe($media->getUrl(FinisterreTask::COVER_CONVERSION))
        ->and($task->coverUrl(thumbnail: false))->toBe($media->getUrl());
});

it('centres a card image nobody has repositioned yet', function() {
    $task = coverTask();
    attachImage($task);

    expect($task->refresh()->coverPosition())->toBe(50.0)
        ->and(coverTask()->coverPosition())->toBe(50.0);
});

it('reads no card image off a task that has none', function() {
    expect(coverTask()->coverUrl())->toBeNull();
});

it('reads no card image when the attachment behind it went missing', function() {
    $task = coverTask();
    $media = attachImage($task);

    // A row removed behind the package's back — the column carries no foreign key.
    Media::query()->whereKey($media->getKey())->delete();

    expect($task->refresh()->coverUrl())->toBeNull();
});

it('does not notify the assignee when only the card image changes', function() {
    Notification::fake();

    $task = coverTask();
    $media = attachImage($task);

    $task->refresh()->update(['cover_media_id' => null]);
    $task->refresh()->update(['cover_media_id' => $media->getKey()]);

    Notification::assertNothingSent();
});
