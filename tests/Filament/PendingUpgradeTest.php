<?php

use Arzcode\Finisterre\Filament\Pages\ManageFinisterreSettings;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Settings\FinisterreSettings;
use Arzcode\Finisterre\Support\PendingUpgrade;
use Arzcode\Finisterre\Support\TaskFollowers;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Workbench\App\Models\User;

const FOLLOWERS_MIGRATION = '2026_10_11_000000_create_finisterre_task_followers_table';
const SMS_KEY_MIGRATION = '2026_10_15_000000_encrypt_finisterre_sms_auth_key';
const RICH_TEXT_MIGRATION = '2026_10_16_000000_sanitize_finisterre_rich_text';

beforeEach(function() {
    config()->set('app.env', 'local');

    $this->actingAs(User::factory()->create());

    if (! Schema::hasTable('settings')) {
        (include __DIR__ . '/../../vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub')->up();
    }

    (include __DIR__ . '/../../database/settings/2026_06_09_000000_create_finisterre_settings.php')->up();
    app()->forgetInstance(FinisterreSettings::class);

    // A host that upgraded and has not migrated yet: it keeps a migrations table,
    // and the table this version adds is not there.
    Artisan::call('migrate:install');
    Schema::drop('finisterre_task_followers');
    TaskFollowers::forgetTable();
});

afterEach(fn() => TaskFollowers::forgetTable());

it('tells whoever configures finisterre, on the board, that an upgrade is pending', function() {
    expect(PendingUpgrade::appliable())->toBe([FOLLOWERS_MIGRATION, SMS_KEY_MIGRATION, RICH_TEXT_MIGRATION]);

    Livewire::test(TasksKanbanBoard::class)
        ->assertActionVisible('finisterreUpgrade')
        ->assertActionHasUrl('finisterreUpgrade', ManageFinisterreSettings::getUrl());
});

it('says nothing on the board to users who cannot configure finisterre', function() {
    FinisterrePlugin::get()->userCanConfigureFinisterre(false);

    Livewire::test(TasksKanbanBoard::class)->assertActionHidden('finisterreUpgrade');
});

it('lists what is pending on the settings page and applies it from there', function() {
    Livewire::test(ManageFinisterreSettings::class)
        ->assertSeeHtml('data-finisterre-upgrade')
        ->assertSee(FOLLOWERS_MIGRATION)
        ->assertActionVisible('applyUpgrade')
        ->callAction('applyUpgrade')
        ->assertNotified(__('finisterre::finisterre.settings.upgrade_applied'))
        ->assertActionHidden('applyUpgrade')
        ->assertDontSee(FOLLOWERS_MIGRATION);

    expect(Schema::hasTable('finisterre_task_followers'))->toBeTrue()
        ->and(DB::table('migrations')->where('migration', FOLLOWERS_MIGRATION)->exists())->toBeTrue()
        ->and(PendingUpgrade::appliable())->toBe([]);
});

it('says so when a migration could not be applied', function() {
    // The table is there already, built by something other than this migration.
    Schema::create('finisterre_task_followers', fn(Blueprint $table) => $table->id());

    Livewire::test(ManageFinisterreSettings::class)
        ->callAction('applyUpgrade')
        ->assertNotified(__('finisterre::finisterre.settings.upgrade_failed'))
        ->assertActionVisible('applyUpgrade');

    expect(PendingUpgrade::appliable())->toBe([FOLLOWERS_MIGRATION, SMS_KEY_MIGRATION, RICH_TEXT_MIGRATION]);
});

it('shows nothing once everything has run', function() {
    PendingUpgrade::apply();

    // The migrations of earlier versions are published stubs, which this test
    // application never published: those are for the console, not the button.
    Livewire::test(ManageFinisterreSettings::class)
        ->assertActionHidden('applyUpgrade')
        ->assertSee(__('finisterre::finisterre.settings.upgrade_console'))
        ->assertDontSee(__('finisterre::finisterre.settings.upgrade_help'));

    Livewire::test(TasksKanbanBoard::class)->assertActionHidden('finisterreUpgrade');
});
