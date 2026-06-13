<?php

use Buzkall\Finisterre\Settings\FinisterreSettings;
use Illuminate\Support\Facades\Schema;

beforeEach(function() {
    if (! Schema::hasTable('settings')) {
        $migration = include base_path('vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub');
        $migration->up();
    }
});

it('runs the finisterre settings migration and seeds the config defaults', function() {
    $migration = include __DIR__ . '/../../database/settings/2026_06_09_000000_create_finisterre_settings.php';
    $migration->up();

    $settings = app(FinisterreSettings::class);

    expect($settings->slug)->toBe('tasks')
        ->and($settings->environments)->toBe('')
        ->and($settings->hidden_statuses)->toBe([])
        ->and($settings->comments_display_avatars)->toBeTrue()
        ->and($settings->sms_notify_priorities)->toBe(['urgent']);
});
