# Finisterre

My helper package

## Installation

You can install the package via composer:

```bash
composer require buzkall/finisterre
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="finisterre-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="finisterre-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="finisterre-views"
```

## Usage

```php
$finisterre = new Buzkall\Finisterre();
echo $finisterre->echoPhrase('Hello, Buzkall!');
```

## Testing

```bash
composer test
```
