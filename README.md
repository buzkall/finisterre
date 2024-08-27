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
    'table_name' => 'finisterre_tasks'
];
```

You can change the name of the table in the config file
You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="finisterre-migrations"
php artisan migrate
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="finisterre-views"
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
