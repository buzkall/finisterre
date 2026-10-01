<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Policies\FinisterreTaskCommentPolicy;
use Arzcode\Finisterre\Policies\FinisterreTaskPolicy;

return [
    // The behavioral options below (environments, slug, hidden_statuses,
    // fallback_notifiable_id, authenticatable_filter_*, exclude_from_global_search,
    // comments.display_avatars, comments.icons.*, sms_notification.*) can be edited
    // at runtime from the Filament settings page; the values here are used as
    // defaults until then.
    // Environments where Finisterre is active (comma-separated, e.g. "local,production").
    // Empty = active in every environment. Editable from the settings page.
    'environments' => env('FINISTERRE_ENVIRONMENTS', ''),
    'table_name'   => 'finisterre_tasks',
    'panel_slug'   => 'admin',
    'slug'         => 'tasks',

    // Name given to tasks in the admin panel: navigation entry, board title,
    // breadcrumbs and the resource's headings. Null keeps the translated
    // defaults ("Task"/"Tasks", or "Issue"/"Issues" for users restricted to
    // their own tasks). Set a plain string, or a translation key you own.
    'label'        => null, // 'Ticket'
    'plural_label' => null, // 'Tickets'

    // Locales to save when creating tags (e.g., ['es', 'ca'])
    'locales' => ['es', 'ca'],

    'model_policy' => FinisterreTaskPolicy::class,

    'authenticatable'            => 'App\Models\User',
    'authenticatable_table_name' => 'users',
    'authenticatable_attribute'  => 'name', // string column, or array like ['name', 'lastname'] for full-name display
    'guard'                      => 'web', // filament

    // fill in case of filtering the assigned user
    'authenticatable_filter_column' => '', // role
    'authenticatable_filter_value'  => '', // admin
    'fallback_notifiable_id'        => 1,

    'hidden_statuses' => [],

    // Keep the package's Filament resources out of the panel's global search,
    // so the host application's own results are not diluted by tasks.
    // Editable from the settings page.
    'exclude_from_global_search' => true,

    // Disk for attachments, card images and the images pasted into descriptions
    // and comments. On 'public' anybody with a file's URL can open it. To keep
    // them private, add a disk outside public/ whose url is /storage/finisterre-files
    // and set its name here: the package then serves every file only to users who
    // can see its task (see "Private attachments" in the README).
    // 'finisterre' => [
    //            'driver'     => 'local',
    //            'root'       => storage_path('app/finisterre-files'),
    //            'url'        => env('APP_URL') . '/storage/finisterre-files',
    //            'visibility' => 'public',
    //            'throw'      => false,
    //        ],
    'attachments_disk' => 'public', // finisterre

    'mail' => [
        // How many of the task's latest history entries (its description and
        // comments, newest first) the notification emails include. Null includes
        // the whole history, 0 leaves it out. Editable from the settings page.
        'history_entries' => null,

        // Reply by email: an answer to a task email becomes a comment on the task.
        // Replies are matched to their task by a signed id in the Message-ID of
        // the email they answer (and, with plus_addressing, in the Reply-To too),
        // and only accepted from an existing user's address. Editable from the
        // settings page.
        'inbound' => [
            'enabled' => env('FINISTERRE_INBOUND_ENABLED', false),

            // imap: `finisterre:fetch-emails` polls a mailbox every minute.
            // resend: Resend posts every received email to /finisterre/inbound/resend.
            'driver' => env('FINISTERRE_INBOUND_DRIVER', 'imap'),

            // The address replies go to (the Reply-To of every task email). It has
            // to land in the mailbox the driver reads.
            'reply_address' => env('FINISTERRE_INBOUND_REPLY_ADDRESS', ''),

            // Send replies to reply_address with the signed task id after a `+`
            // (tasks+12-3f9a…@example.com). Only turn it on when the mailbox
            // accepts plus addresses; it helps when the mail service rewrites the
            // Message-ID (Amazon SES does).
            'plus_addressing' => env('FINISTERRE_INBOUND_PLUS_ADDRESSING', false),

            'imap' => [
                'host'       => env('FINISTERRE_IMAP_HOST', ''),
                'port'       => (int)env('FINISTERRE_IMAP_PORT', 993),
                'encryption' => env('FINISTERRE_IMAP_ENCRYPTION', 'ssl'), // ssl | tls | starttls | none
                'username'   => env('FINISTERRE_IMAP_USERNAME', ''),
                'password'   => env('FINISTERRE_IMAP_PASSWORD'),
                'folder'     => env('FINISTERRE_IMAP_FOLDER', 'INBOX'),
            ],

            'resend' => [
                // Falls back to services.resend.key, the one the Resend mailer uses.
                'api_key'        => env('FINISTERRE_RESEND_API_KEY'),
                'webhook_secret' => env('FINISTERRE_RESEND_WEBHOOK_SECRET'),
            ],
        ],
    ],

    'task_changes_table_name' => 'finisterre_task_changes',

    'subtasks' => [
        'table_name' => 'finisterre_subtasks',

        // Email the assignee when somebody else changes their checklist. The
        // first change opens a window of this many minutes, and the digest
        // reports how the checklist looks at the end of it against how it
        // looked at the start. Both are editable from the settings page.
        //
        // Grouping needs a queue worker and a shared, lock-capable cache store
        // (redis, memcached, database, or file on a single server). Under
        // QUEUE_CONNECTION=sync the delay is ignored and every change sends its
        // own email; see the README.
        'notify'                     => true,
        'notification_delay_minutes' => 5,
    ],

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
