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
    // 3. Add the route trait to the bootstrap app.php file in withRouting
    // then: function() {
    //         (new class{ use AttachmentsRoute;})->attachmentsRoute();
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
    ]
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

## Testing

```bash
composer test
```
