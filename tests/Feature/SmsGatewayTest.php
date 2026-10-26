<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\TaskSmsNotification;
use Arzcode\Finisterre\Settings\FinisterreSettings;
use Arzcode\Finisterre\Support\SafeUrl;
use Arzcode\Finisterre\Support\SettingsConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function() {
    config([
        'finisterre.active'                     => false,
        'finisterre.table_name'                 => 'finisterre_tasks',
        'finisterre.task_changes_table_name'    => 'finisterre_task_changes',
        'finisterre.authenticatable'            => User::class,
        'finisterre.authenticatable_table_name' => 'users',
        'finisterre.authenticatable_attribute'  => 'name',
    ]);

    if (! Schema::hasTable('finisterre_task_changes')) {
        Schema::create('finisterre_task_changes', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
});

it('keeps the settings page closed until the host allows somebody', function() {
    expect((new FinisterrePlugin)->canConfigure())->toBeFalse()
        ->and((new FinisterrePlugin)->userCanConfigureFinisterre(fn() => true)->canConfigure())->toBeTrue();
});

it('accepts only public https addresses for the sms gateway', function(string $url, bool $accepted) {
    expect(SafeUrl::isPublicHttps($url))->toBe($accepted);
})->with([
    'the default gateway'    => ['https://api.smsarena.es/http/sms.php', true],
    'a public ip'            => ['https://203.0.113.10/sms', true],
    'plain http'             => ['http://api.smsarena.es/http/sms.php', false],
    'another scheme'         => ['ftp://api.smsarena.es/sms', false],
    'no scheme'              => ['api.smsarena.es/sms', false],
    'loopback'               => ['https://127.0.0.1/sms', false],
    'loopback v6'            => ['https://[::1]/sms', false],
    'mapped loopback'        => ['https://[::ffff:127.0.0.1]/sms', false],
    'localhost'              => ['https://localhost/sms', false],
    'a name without a dot'   => ['https://intranet/sms', false],
    'an internal name'       => ['https://db.internal/sms', false],
    'a private range'        => ['https://10.0.0.5/sms', false],
    'another private range'  => ['https://192.168.1.1/sms', false],
    'the cloud metadata ip'  => ['https://169.254.169.254/latest/meta-data', false],
    'credentials in the url' => ['https://user:pass@api.smsarena.es/sms', false],
    'whitespace'             => ['https://api.smsarena.es/ sms', false],
    'nothing'                => ['', false],
]);

it('rejects a name that resolves to a private address', function() {
    SafeUrl::resolveUsing(fn(): array => ['203.0.113.10', '10.1.2.3']);

    expect(SafeUrl::isPublicHttps('https://rebind.example.com/sms'))->toBeFalse();

    SafeUrl::resolveUsing(fn(): array => ['203.0.113.10']);

    expect(SafeUrl::isPublicHttps('https://rebind.example.com/sms'))->toBeTrue();
});

function smsTask(): FinisterreTask
{
    return FinisterreTask::factory()->create([
        'priority'   => TaskPriorityEnum::Urgent,
        'creator_id' => User::factory()->create()->id,
    ]);
}

it('does not send the gateway key to an address that is not a public https one', function() {
    Http::fake();
    Exceptions::fake();

    config([
        'finisterre.sms_notification.enabled'           => true,
        'finisterre.sms_notification.notify_priorities' => [TaskPriorityEnum::Urgent],
        'finisterre.sms_notification.url'               => 'https://169.254.169.254/latest/meta-data',
        'finisterre.sms_notification.auth_key'          => 'top-secret',
    ]);

    (new TaskSmsNotification(smsTask()))->toSms(User::factory()->create());

    Http::assertNothingSent();
    Exceptions::assertReported(RuntimeException::class);
});

function sendSmsTo(string $url): void
{
    config([
        'finisterre.sms_notification.enabled'           => true,
        'finisterre.sms_notification.notify_priorities' => [TaskPriorityEnum::Urgent],
        'finisterre.sms_notification.url'               => $url,
        'finisterre.sms_notification.auth_key'          => 'top-secret',
    ]);

    (new TaskSmsNotification(smsTask()))->toSms(User::factory()->create());
}

it('does not follow a redirect from the sms gateway', function() {
    SafeUrl::resolveUsing(fn(): array => ['203.0.113.10']);
    Http::fake(['*' => Http::sequence()
        ->push('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data'])
        ->push('leaked')]);

    Exceptions::fake();

    sendSmsTo('https://sms.example.com/send');

    Exceptions::assertReported(fn(RuntimeException $exception): bool => str_contains($exception->getMessage(), 'redirect'));
    Http::assertSentCount(1);
    Http::assertSent(fn($request): bool => str_starts_with($request->url(), 'https://sms.example.com/send'));
});

it('sends nothing to a gateway whose name does not resolve', function() {
    SafeUrl::resolveUsing(fn(): array => []);
    Http::fake();

    expect(fn() => sendSmsTo('https://sms.example.com/send'))->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('pins the gateway name to the addresses that were checked', function() {
    expect(SafeUrl::curlResolve('https://sms.example.com/send', ['203.0.113.10', '2001:db8::1']))
        ->toBe(['sms.example.com:443:203.0.113.10,[2001:db8::1]'])
        ->and(SafeUrl::curlResolve('https://sms.example.com:8443/send', ['203.0.113.10']))
        ->toBe(['sms.example.com:8443:203.0.113.10'])
        // An address in the URL needs no pinning.
        ->and(SafeUrl::curlResolve('https://203.0.113.10/send', ['203.0.113.10']))->toBe([]);
});

it('stores the gateway key encrypted', function() {
    expect(FinisterreSettings::encrypted())->toContain('sms_auth_key');
});

it('encrypts a gateway key stored as plain text, once', function() {
    $table = 'settings';

    if (! Schema::hasTable($table)) {
        (include __DIR__ . '/../../vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub')->up();
    }

    (include __DIR__ . '/../../database/settings/2026_06_09_000000_create_finisterre_settings.php')->up();

    $row = fn() => DB::table($table)->where('group', 'finisterre')->where('name', 'sms_auth_key');

    // What an installation of an earlier version holds.
    $row()->update(['payload' => json_encode('plain-key')]);
    app()->forgetInstance(FinisterreSettings::class);

    $migration = include __DIR__ . '/../../database/auto/2026_10_15_000000_encrypt_finisterre_sms_auth_key.php';
    $migration->up();

    $stored = json_decode((string)$row()->value('payload'), true);

    expect($stored)->not->toBe('plain-key')
        ->and(decrypt($stored))->toBe('plain-key')
        ->and(app(FinisterreSettings::class)->sms_auth_key)->toBe('plain-key');

    // A second run, or a fresh install that was seeded encrypted, must not encrypt again.
    $migration->up();

    expect(json_decode((string)$row()->value('payload'), true))->toBe($stored);
});

it('does not touch an empty gateway key, or settings that are not there yet', function() {
    $migration = include __DIR__ . '/../../database/auto/2026_10_15_000000_encrypt_finisterre_sms_auth_key.php';

    $migration->up();

    if (! Schema::hasTable('settings')) {
        (include __DIR__ . '/../../vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub')->up();
    }

    (include __DIR__ . '/../../database/settings/2026_06_09_000000_create_finisterre_settings.php')->up();

    $migration->up();

    expect(DB::table('settings')->where('group', 'finisterre')->where('name', 'sms_auth_key')->value('payload'))->toBe('null');
});

it('still loads the settings, encrypting the gateway key, when the migration has not run yet', function() {
    if (! Schema::hasTable('settings')) {
        (include __DIR__ . '/../../vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub')->up();
    }

    (include __DIR__ . '/../../database/settings/2026_06_09_000000_create_finisterre_settings.php')->up();

    DB::table('settings')->where('group', 'finisterre')->where('name', 'sms_auth_key')->update(['payload' => json_encode('plain-key')]);
    app()->forgetInstance(FinisterreSettings::class);

    SettingsConfig::apply();

    expect(config('finisterre.sms_notification.auth_key'))->toBe('plain-key')
        ->and(decrypt(json_decode((string)DB::table('settings')->where('name', 'sms_auth_key')->value('payload'), true)))->toBe('plain-key');
});
