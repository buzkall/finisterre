# Finisterre

My helper package

## Installation

You can install the package via composer:

```bash
composer require buzkall/finisterre
```

**Critical:** After installation, you must publish Filament assets for the kanban board to work:

```bash
php artisan filament:assets
```

Without this step, the kanban board will load but drag-and-drop functionality will not work due to missing JavaScript assets.

You can publish the config file with:

```bash
php artisan vendor:publish --tag="finisterre-config"
```

By default, the package will not be active; this can be changed by adding the following to your .env file

```bash
FINISTERRE_ACTIVE=true
```

**Important:** If your Filament panel uses a different ID than `admin`, add this to your `.env`:

```bash
FINISTERRE_PANEL_SLUG=your-panel-id
```

To find your panel ID, check your `PanelProvider.php` for `->id('...')`. For example, if your panel uses
`->id('filament')`, set `FINISTERRE_PANEL_SLUG=filament`.

You can change the name of the table in the config file
You need to publish and run the migrations with:

```bash
php artisan vendor:publish --tag="finisterre-migrations"
php artisan migrate
```

If you don't already have the spatie tags package, publish the migrations

```bash
php artisan vendor:publish --provider="Spatie\Tags\TagsServiceProvider" --tag="tags-migrations"
php artisan migrate
```

Same for spatie media package

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

The tasks are linked to users, but the project can have a different model for users.
You can change the model in the config file and the name attribute column
Also, there is a trait to be included in the user's model

```
use Buzkall\Finisterre\Traits\FinisterreUserTrait;
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="finisterre-views"
```

**Important:** You must publish Filament assets (including Flowforge kanban JavaScript) with:

```bash
php artisan filament:assets
```

This command publishes all Filament plugin assets, including the Flowforge kanban board JavaScript required for drag-and-drop functionality. **Run this command after installing or updating the package**, otherwise the kanban board will load but drag-and-drop will not work.

The package comes with a default policy for the tasks that can be overridden in the config file and set your own policy

```php
'model_policy' => Buzkall\Finisterre\Policies\FinisterreTaskPolicy::class,
``` 

## Usage

Add the plugin to your panel provider and specify the permissions

```php

use Buzkall\Finisterre\FinisterrePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FinisterrePlugin::make()
                ->userCanViewAllTasks(fn() => auth()->user()?->hasRole(RoleEnum::Admin))
                ->userCanViewOnlyTheirTasks(fn() => auth()->user()?->hasAnyRole([RoleEnum::Editor, RoleEnum::Manager])),
        ])
    ])
}
```

## SMS notifications

Using smsarena.es as provider.
By default, only notifies tasks on creation with priority TaskPriorityEnum::Urgent
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

TODO

## Development

To build the CSS assets after making changes to Tailwind classes:

```bash
npm run build:styles
```

## Testing

```bash
composer test
```
