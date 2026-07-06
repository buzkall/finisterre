<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\FinisterreServiceProvider;

/**
 * Simulate a host app whose published config/finisterre.php declares only a
 * subset of keys, then run the provider's recursive merge over it. We invoke
 * registerPackageConfigs() directly to exercise the merge in isolation.
 */
function mergePublishedConfig(array $published): void
{
    config()->set('finisterre', $published);

    new FinisterreServiceProvider(app())->registerPackageConfigs();
}

it('falls back to vendor defaults for top-level keys the app omits', function() {
    mergePublishedConfig([
        'table_name' => 'tasks',
    ]);

    expect(config('finisterre.slug'))->toBe('tasks')
        ->and(config('finisterre.table_name'))->toBe('tasks');
});

it('deep-merges nested arrays instead of replacing them wholesale', function() {
    mergePublishedConfig([
        'comments' => [
            'table_name'   => 'task_comments',
            'model_policy' => 'HostPolicy',
        ],
    ]);

    // Nested keys the app omitted come from the vendor defaults...
    expect(config('finisterre.comments.display_avatars'))->toBeTrue()
        ->and(config('finisterre.comments.icons.action'))->toBe('heroicon-o-chat-bubble-left-right')
        // ...while the app's nested overrides are preserved.
        ->and(config('finisterre.comments.model_policy'))->toBe('HostPolicy')
        ->and(config('finisterre.comments.table_name'))->toBe('task_comments');
});

it('replaces a list-style key wholesale when the app defines a shorter list', function() {
    // Vendor default locales is ['es', 'ca']; a naive recursive merge would
    // keep the trailing 'ca' by index. The list guard must drop it.
    mergePublishedConfig([
        'locales' => ['es'],
    ]);

    expect(config('finisterre.locales'))->toBe(['es']);
});

it('replaces a nested list-style key wholesale', function() {
    // Vendor default notify_priorities is [Urgent]; an empty app list must win
    // rather than leaking the vendor entry through index merging.
    mergePublishedConfig([
        'sms_notification' => [
            'notify_priorities' => [],
        ],
    ]);

    expect(config('finisterre.sms_notification.notify_priorities'))->toBe([])
        // Sibling nested keys still fall back to vendor defaults.
        ->and(config('finisterre.sms_notification.url'))->toBe('https://api.smsarena.es/http/sms.php');
});

it('keeps a list-style key from the vendor defaults when the app omits it', function() {
    mergePublishedConfig([
        'table_name' => 'tasks',
    ]);

    expect(config('finisterre.locales'))->toBe(['es', 'ca'])
        ->and(config('finisterre.sms_notification.notify_priorities'))->toBe([TaskPriorityEnum::Urgent]);
});
