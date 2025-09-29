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

## Testing

```bash
composer test
```
