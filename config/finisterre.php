<?php

return [
    'active'     => env('FINISTERRE_ACTIVE', false),
    'table_name' => 'finisterre_tasks',
    'slug'       => 'tasks',

    'model_policy' => Buzkall\Finisterre\Policies\FinisterreTaskPolicy::class,

    'authenticatable'            => \App\Models\User::class, // @phpstan-ignore-line
    'authenticatable_table_name' => 'users',
    'authenticatable_attribute'  => 'name',
    'guard'                      => 'web', // filament

    // fill in case of filtering the assigned user
    'authenticatable_filter_column' => '', // role
    'authenticatable_filter_value'  => '', // admin
    'fallback_notifiable_id'        => 1,

    'hidden_statuses' => [],

    // To set the attachments as private:
    // 1. Change the 'attachments_disk' to 'finisterre',
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
    //          (new Buzkall\Finisterre\Controllers\FilamentRouteController)();
    //       }
    'attachments_disk' => 'public', // finisterre

    'comments' => [
        'table_name'          => 'finisterre_task_comments',
        'model_policy'        => Buzkall\Finisterre\Policies\FinisterreTaskCommentPolicy::class,
        'display_avatars'     => true,
        'user_name_attribute' => 'name',

        // Icons used in the comments component.
        'icons' => [
            'action' => 'heroicon-s-chat-bubble-left-right',
            'delete' => 'heroicon-s-trash',
            'empty'  => 'heroicon-s-chat-bubble-left-right',
        ],

        // Options: 'rich', 'markdown'
        'editor' => 'rich',

        // Rich editor toolbar buttons that are available to users.
        'toolbar_buttons' => [
            'blockquote',
            'bold',
            'bulletList',
            'codeBlock',
            'italic',
            'link',
            'orderedList',
            'redo',
            'strike',
            'underline',
            'undo',
            'attachFiles',
        ],
    ],

    'sms_notification' => [
        'enabled'           => env('FINISTERRE_SMS_ENABLED', false),
        'url'               => 'https://api.smsarena.es/http/sms.php',
        'auth_key'          => env('FINISTERRE_SMS_AUTH_KEY'),
        'sender'            => env('FINISTERRE_SMS_SENDER'),
        'notify_to'         => env('FINISTERRE_SMS_NOTIFY_TO'),
        'notify_priorities' => [Buzkall\Finisterre\Enums\TaskPriorityEnum::Urgent],
    ]
];
