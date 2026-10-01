<?php

use Arzcode\Finisterre\Filament\Pages\ManageFinisterreSettings;
use Arzcode\Finisterre\Settings\FinisterreSettings;
use Illuminate\Database\Schema\Blueprint;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    config()->set('app.env', 'local');

    $this->actingAs(User::factory()->create());

    // A faked settings object still saves through the database repository, so
    // the page's save() needs the table spatie/laravel-settings writes to.
    $this->createTableIfMissing('settings', function(Blueprint $table) {
        $table->id();
        $table->string('group');
        $table->string('name');
        $table->boolean('locked')->default(false);
        $table->json('payload');
        $table->timestamps();
        $table->unique(['group', 'name']);
    });

    FinisterreSettings::fake([
        'environments'                        => '',
        'slug'                                => 'tasks',
        'hidden_statuses'                     => [],
        'fallback_notifiable_id'              => 1,
        'authenticatable_filter_column'       => '',
        'authenticatable_filter_value'        => '',
        'exclude_from_global_search'          => true,
        'subtasks_notify'                     => true,
        'subtasks_notification_delay_minutes' => 5,
        'mail_history_entries'                => null,
        'comments_display_avatars'            => true,
        'comments_icon_action'                => 'heroicon-o-chat-bubble-left-right',
        'comments_icon_delete'                => 'heroicon-o-trash',
        'comments_icon_empty'                 => 'heroicon-o-chat-bubble-left-right',
        'sms_enabled'                         => false,
        'sms_url'                             => 'https://example.test/sms',
        'sms_auth_key'                        => null,
        'sms_sender'                          => null,
        'sms_notify_to'                       => null,
        'sms_notify_priorities'               => [],
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
    ]);
});

it('offers the global search toggle filled from the stored settings', function() {
    Livewire::test(ManageFinisterreSettings::class)
        ->assertFormFieldExists('exclude_from_global_search')
        ->assertSet('data.exclude_from_global_search', true);
});

it('saves with sms disabled and keeps the stored sms values', function() {
    Livewire::test(ManageFinisterreSettings::class)
        ->set('data.slug', 'my-tasks')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(FinisterreSettings::class);

    expect($settings->slug)->toBe('my-tasks')
        ->and($settings->sms_enabled)->toBeFalse()
        ->and($settings->sms_url)->toBe('https://example.test/sms');
});

it('saves with subtask notifications off and keeps the stored delay', function() {
    Livewire::test(ManageFinisterreSettings::class)
        ->set('data.subtasks_notify', false)
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(FinisterreSettings::class);

    expect($settings->subtasks_notify)->toBeFalse()
        ->and($settings->subtasks_notification_delay_minutes)->toBe(5);
});

it('saves the number of history entries in emails, and an empty field as the whole history', function() {
    Livewire::test(ManageFinisterreSettings::class)
        ->set('data.mail_history_entries', '3')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(FinisterreSettings::class)->mail_history_entries)->toBe(3);

    Livewire::test(ManageFinisterreSettings::class)
        ->set('data.mail_history_entries', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(FinisterreSettings::class)->mail_history_entries)->toBeNull();
});

it('saves the reply by email settings of the chosen driver and keeps the other driver\'s', function() {
    Livewire::test(ManageFinisterreSettings::class)
        ->set('data.inbound_enabled', true)
        ->set('data.inbound_driver', 'resend')
        ->set('data.inbound_reply_address', 'tasks@example.com')
        ->set('data.inbound_resend_webhook_secret', 'whsec_c2VjcmV0')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(FinisterreSettings::class);

    expect($settings->inbound_enabled)->toBeTrue()
        ->and($settings->inbound_driver)->toBe('resend')
        ->and($settings->inbound_reply_address)->toBe('tasks@example.com')
        ->and($settings->inbound_resend_webhook_secret)->toBe('whsec_c2VjcmV0')
        ->and($settings->inbound_imap_folder)->toBe('INBOX')
        ->and($settings->inbound_imap_port)->toBe(993);
});

it('asks for the reply address once reply by email is on', function() {
    Livewire::test(ManageFinisterreSettings::class)
        ->set('data.inbound_enabled', true)
        ->call('save')
        ->assertHasErrors(['data.inbound_reply_address']);
});

it('never sends the stored secrets to the browser and keeps them when their field is left blank', function() {
    $settings = app(FinisterreSettings::class);
    $settings->inbound_resend_api_key = 're_secret';
    $settings->inbound_resend_webhook_secret = 'whsec_old';
    $settings->save();

    Livewire::test(ManageFinisterreSettings::class)
        ->assertSet('data.inbound_resend_api_key', null)
        ->assertSet('data.inbound_resend_webhook_secret', null)
        ->set('data.inbound_enabled', true)
        ->set('data.inbound_driver', 'resend')
        ->set('data.inbound_reply_address', 'tasks@example.com')
        ->assertDontSee('re_secret')
        ->assertDontSee('whsec_old')
        ->assertSee(__('finisterre::finisterre.settings.secret_stored'))
        ->set('data.inbound_resend_webhook_secret', 'whsec_new')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(FinisterreSettings::class)->refresh();

    expect($settings->inbound_resend_api_key)->toBe('re_secret')
        ->and($settings->inbound_resend_webhook_secret)->toBe('whsec_new');
});
