<?php

use App\Models\User;
use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Policies\FinisterreTaskCommentPolicy;
use Arzcode\Finisterre\Policies\FinisterreTaskPolicy;

return [
    // The behavioral options below (environments, slug, hidden_statuses,
    // fallback_notifiable_id, authenticatable_filter_*, comments.display_avatars,
    // comments.icons.*, sms_notification.*) can be edited at runtime from the
    // Filament settings page; the values here are used as defaults until then.
    // Environments where Finisterre is active (comma-separated, e.g. "local,production").
    // Empty = active in every environment. Editable from the settings page.
    'environments' => env('FINISTERRE_ENVIRONMENTS', ''),
    'table_name'   => 'finisterre_tasks',
    'panel_slug'   => 'admin',
    'slug'         => 'tasks',

    // Locales to save when creating tags (e.g., ['es', 'ca'])
    'locales' => ['es', 'ca'],

    'model_policy' => FinisterreTaskPolicy::class,

    'authenticatable'            => User::class, // @phpstan-ignore-line
    'authenticatable_table_name' => 'users',
    'authenticatable_attribute'  => 'name', // string column, or array like ['name', 'lastname'] for full-name display
    'guard'                      => 'web', // filament

    // fill in case of filtering the assigned user
    'authenticatable_filter_column' => '', // role
    'authenticatable_filter_value'  => '', // admin
    'fallback_notifiable_id'        => 1,

    'hidden_statuses' => [],

    // To set the attachments as private:
    // 1. Change the 'attachments_disk' to 'finisterre'
    // 2. Add a disk in filesystem named 'finisterre' with url /storage/finisterre and visibility public
    // 'finisterre' => [
    //            'driver'     => 'local',
    //            'root'       => storage_path('app/finisterre-files'),
    //            'url'        => env('APP_URL') . '/storage/finisterre-files',
    //            'visibility' => 'public',
    //            'throw'      => false,
    //        ],
    // 3. Add the route controller to the bootstrap app.php file in withRouting
    // then: function() {
    //          (new Arzcode\FinisterrePlugin\Controllers\FilamentRouteController)();
    //       }
    'attachments_disk' => 'public', // finisterre

    'task_changes_table_name' => 'finisterre_task_changes',

    'comments' => [
        'table_name'      => 'finisterre_task_comments',
        'model_policy'    => FinisterreTaskCommentPolicy::class,
        'display_avatars' => true,

        // Icons used in the comments' component.
        'icons' => [
            'action' => 'heroicon-o-chat-bubble-left-right',
            'delete' => 'heroicon-o-trash',
            'empty'  => 'heroicon-o-chat-bubble-left-right',
        ],
    ],

    'sms_notification' => [
        'enabled'           => env('FINISTERRE_SMS_ENABLED', false),
        'url'               => 'https://api.smsarena.es/http/sms.php',
        'auth_key'          => env('FINISTERRE_SMS_AUTH_KEY'),
        'sender'            => env('FINISTERRE_SMS_SENDER'),
        'notify_to'         => env('FINISTERRE_SMS_NOTIFY_TO'),
        'notify_priorities' => [TaskPriorityEnum::Urgent],
    ],
];
