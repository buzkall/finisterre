<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Settings\FinisterreSettings;
use Arzcode\Finisterre\Support\SettingsConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;

// fake() needs every declared property, otherwise spatie loads the missing
// ones from the (absent) settings table. This provides sensible defaults so
// individual tests only override the keys they care about.
function fakeFinisterreSettings(array $overrides = []): void
{
    FinisterreSettings::fake(array_merge([
        'environments'                        => '',
        'slug'                                => 'board',
        'hidden_statuses'                     => ['backlog', 'rejected'],
        'fallback_notifiable_id'              => 7,
        'authenticatable_filter_column'       => 'role',
        'authenticatable_filter_value'        => 'admin',
        'exclude_from_global_search'          => true,
        'subtasks_notify'                     => true,
        'subtasks_notification_delay_minutes' => 5,
        'mail_history_entries'                => 3,
        'comments_display_avatars'            => false,
        'comments_icon_action'                => 'heroicon-s-bolt',
        'comments_icon_delete'                => 'heroicon-s-fire',
        'comments_icon_empty'                 => 'heroicon-s-moon',
        'sms_enabled'                         => true,
        'sms_url'                             => 'https://example.test/sms',
        'sms_auth_key'                        => 'secret-key',
        'sms_sender'                          => 'ACME',
        'sms_notify_to'                       => '600600600',
        'sms_notify_priorities'               => ['urgent', 'high'],
        'inbound_enabled'                     => false,
        'inbound_driver'                      => 'imap',
        'inbound_reply_address'               => '',
        'inbound_plus_addressing'             => false,
        'inbound_imap_host'                   => '',
        'inbound_imap_port'                   => 993,
        'inbound_imap_encryption'             => 'ssl',
        'inbound_imap_username'               => '',
        'inbound_imap_password'               => null,
        'inbound_imap_folder'                 => 'INBOX',
        'inbound_resend_api_key'              => null,
        'inbound_resend_webhook_secret'       => null,
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
        ->and(config('finisterre.exclude_from_global_search'))->toBeTrue()
        ->and(config('finisterre.mail.history_entries'))->toBe(3)
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

describe('after an upgrade that added settings', function() {
    beforeEach(function() {
        if (! Schema::hasTable('settings')) {
            (include __DIR__ . '/../../vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub')->up();
        }

        // An installed host: every setting of the version it installed has its row.
        (include __DIR__ . '/../../database/settings/2026_06_09_000000_create_finisterre_settings.php')->up();

        $this->forget = function(string $name): void {
            DB::table('settings')->where('group', 'finisterre')->where('name', $name)->delete();
            app()->forgetInstance(FinisterreSettings::class);
        };
    });

    it('seeds the settings the stored ones predate instead of turning finisterre off', function() {
        DB::table('settings')->where('group', 'finisterre')->where('name', 'slug')->update(['payload' => json_encode('my-board')]);
        ($this->forget)('inbound_enabled');
        ($this->forget)('mail_history_entries');

        SettingsConfig::apply();

        expect(config('finisterre.active'))->toBeTrue()
            // What was stored is kept; only the gaps are filled.
            ->and(config('finisterre.slug'))->toBe('my-board')
            ->and(config('finisterre.mail.inbound.enabled'))->toBeFalse()
            ->and(SettingsConfig::missing())->toBe([]);
    });

    it('stays inactive when none of its settings are stored', function() {
        DB::table('settings')->where('group', 'finisterre')->delete();
        app()->forgetInstance(FinisterreSettings::class);
        config(['finisterre.environments' => '']);

        SettingsConfig::apply();

        expect(config('finisterre.active'))->toBeFalse()
            ->and(DB::table('settings')->where('group', 'finisterre')->count())->toBe(0);
    });

    it('does not trip over a setting another request seeded first', function() {
        // The check says the row is missing; by the time it is added, it is there.
        app()->bind(SettingsMigrator::class, fn() => new class(app(SettingsRepository::class)) extends SettingsMigrator
        {
            public function exists(string $property): bool
            {
                return $property !== 'finisterre.inbound_enabled' && parent::exists($property);
            }
        });

        expect(SettingsConfig::seedMissing())->toBe(0)
            ->and(DB::table('settings')->where('group', 'finisterre')->where('name', 'inbound_enabled')->count())->toBe(1);
    });
});
