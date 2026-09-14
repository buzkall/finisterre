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
