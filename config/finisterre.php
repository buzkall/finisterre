<?php

return [
    'active'     => env('FINISTERRE_ACTIVE', false),
    'table_name' => 'finisterre_tasks',
    'slug'       => 'tasks',

    'model_policy' => Buzkall\Finisterre\Policies\FinisterreTaskPolicy::class,

    'authenticatable'            => \App\Models\User::class,
    'authenticatable_table_name' => 'users',
    'user_name_attribute'        => 'name',

    'hidden_statuses' => [],

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
    ]
];
