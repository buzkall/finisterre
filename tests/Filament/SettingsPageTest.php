<?php

use Arzcode\Finisterre\Filament\Pages\ManageFinisterreSettings;
use Arzcode\Finisterre\Settings\FinisterreSettings;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    config()->set('app.env', 'local');

    $this->actingAs(User::factory()->create());

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
