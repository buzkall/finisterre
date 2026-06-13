<?php

use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Settings\FinisterreSettings;
use Buzkall\Finisterre\Support\SettingsConfig;

// fake() needs every declared property, otherwise spatie loads the missing
// ones from the (absent) settings table. This provides sensible defaults so
// individual tests only override the keys they care about.
function fakeFinisterreSettings(array $overrides = []): void
{
    FinisterreSettings::fake(array_merge([
        'environments'                  => '',
        'slug'                          => 'board',
        'hidden_statuses'               => ['backlog', 'rejected'],
        'fallback_notifiable_id'        => 7,
        'authenticatable_filter_column' => 'role',
        'authenticatable_filter_value'  => 'admin',
        'comments_display_avatars'      => false,
        'comments_icon_action'          => 'heroicon-s-bolt',
        'comments_icon_delete'          => 'heroicon-s-fire',
        'comments_icon_empty'           => 'heroicon-s-moon',
        'sms_enabled'                   => true,
        'sms_url'                       => 'https://example.test/sms',
        'sms_auth_key'                  => 'secret-key',
        'sms_sender'                    => 'ACME',
        'sms_notify_to'                 => '600600600',
        'sms_notify_priorities'         => ['urgent', 'high'],
    ], $overrides));
}

it('overrides the finisterre config with stored settings', function() {
    fakeFinisterreSettings();

    SettingsConfig::apply();

    expect(config('finisterre.active'))->toBeTrue()
        ->and(config('finisterre.slug'))->toBe('board')
        ->and(config('finisterre.hidden_statuses'))->toBe(['backlog', 'rejected'])
        ->and(config('finisterre.fallback_notifiable_id'))->toBe(7)
        ->and(config('finisterre.authenticatable_filter_column'))->toBe('role')
        ->and(config('finisterre.authenticatable_filter_value'))->toBe('admin')
        ->and(config('finisterre.comments.display_avatars'))->toBeFalse()
        ->and(config('finisterre.comments.icons.action'))->toBe('heroicon-s-bolt')
        ->and(config('finisterre.comments.icons.delete'))->toBe('heroicon-s-fire')
        ->and(config('finisterre.comments.icons.empty'))->toBe('heroicon-s-moon')
        ->and(config('finisterre.sms_notification.enabled'))->toBeTrue()
        ->and(config('finisterre.sms_notification.url'))->toBe('https://example.test/sms')
        ->and(config('finisterre.sms_notification.auth_key'))->toBe('secret-key')
        ->and(config('finisterre.sms_notification.sender'))->toBe('ACME')
        ->and(config('finisterre.sms_notification.notify_to'))->toBe('600600600')
        ->and(config('finisterre.sms_notification.notify_priorities'))
        ->toBe([TaskPriorityEnum::Urgent, TaskPriorityEnum::High]);
});

it('marks finisterre active when the environments list is empty', function() {
    fakeFinisterreSettings(['environments' => '']);

    SettingsConfig::apply();

    expect(config('finisterre.active'))->toBeTrue();
});

it('marks finisterre active only when the current environment is listed', function() {
    // The test suite runs in the "testing" environment.
    fakeFinisterreSettings(['environments' => 'local, testing']);
    SettingsConfig::apply();
    expect(config('finisterre.active'))->toBeTrue();

    fakeFinisterreSettings(['environments' => 'local,production']);
    SettingsConfig::apply();
    expect(config('finisterre.active'))->toBeFalse();
});

it('keeps the config-file defaults and stays inactive when settings are not migrated', function() {
    config(['finisterre.slug' => 'tasks', 'finisterre.environments' => '']);

    // No settings table / no stored values — apply() must swallow the error and,
    // since the plugin isn't really installed, leave it inactive.
    SettingsConfig::apply();

    expect(config('finisterre.slug'))->toBe('tasks')
        ->and(config('finisterre.active'))->toBeFalse();
});

it('activates from an explicit env list even when settings are not migrated', function() {
    // The suite runs in the "testing" environment.
    config(['finisterre.environments' => 'testing']);

    // No settings table — apply() falls back to the config/env value.
    SettingsConfig::apply();

    expect(config('finisterre.active'))->toBeTrue();
});
