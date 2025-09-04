# Finisterre

My helper package

## Installation

You can install the package via composer:

```bash
composer require buzkall/finisterre
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="finisterre-config"
```

This is the contents of the published config file:

```php
return [
    'active'     => env('FINISTERRE_ACTIVE', false),
    'table_name' => 'finisterre_tasks',
    'slug'       => 'tasks',

    'model_policy' => Buzkall\Finisterre\Policies\FinisterreTaskPolicy::class,

    'authenticatable'            => \App\Models\User::class,
    'authenticatable_table_name' => 'users',
    'authenticatable_attribute'  => 'name',

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
    'attachments_disk' => 'finisterre',

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
    ],

    'restrict_task_reports' => false,

    'policies' => [
        'task_report' => [
            'before_function' => null, // Set to a closure or string for custom logic
        ],
    ],
];
```

By default, the package will not be active, this can be changed adding the following to your .env file

```bash
FINISTERRE_ACTIVE=true
```

You can change the name of the table in the config file
You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="finisterre-migrations"
php artisan migrate
```

If you don't already have the spatie tags package, publish the migrations

```bash
php artisan vendor:publish --provider="Spatie\Tags\TagsServiceProvider" --tag="tags-migrations"
php artisan migrate
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="finisterre-views"
```

The package comes with a default policy for the tasks, that can be overridden in the config file and set your own policy

```php
'model_policy' => Buzkall\Finisterre\Policies\FinisterreTaskPolicy::class,
``` 

## Usage

Add the plugin to your panel provider

```php

use Buzkall\Finisterre\Finisterre;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            Finisterre::make(),
        ])
    ])
}
```

## SMS notifications

Using smsarena.es as provider.
By default only notifies tasks on creation with priority TaskPriorityEnum::Urgent
That can be changed in the config file

Add to your .env file the following:

```
# Finisterre
FINISTERRE_SMS_ENABLED=false
FINISTERRE_SMS_AUTH_KEY=CHANGE
FINISTERRE_SMS_SENDER=CHANGE
FINISTERRE_SMS_NOTIFY_TO=CHANGE
```

## Role restriction for Task Reports

Here's how you can use the configurable policy feature in your Laravel application:

### 1. Configure the Policy Logic

In your `config/finisterre.php` file (needs to be a string to be able to cache config):

```php
'restrict_task_reports_callback' =>  '$user->hasRole(App\Enums\RoleEnum::Admin)'
```

### 2. The Policy Will Automatically Use Your Logic

The `FinisterreTaskReportPolicy` will now use your custom logic in its `before` method.

## Testing

```bash
composer test
```
