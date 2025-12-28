# Laravel Smart Migrations

Intelligently manage Laravel migrations with safe rollback, analysis, validation, and comprehensive tools for table and model-based operations.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sirval/laravel-smart-migrations.svg?style=flat-square)](https://packagist.org/packages/sirval/laravel-smart-migrations)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sirval/laravel-smart-migrations/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sirval/laravel-smart-migrations/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/sirval/laravel-smart-migrations/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/sirval/laravel-smart-migrations/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/sirval/laravel-smart-migrations.svg?style=flat-square)](https://packagist.org/packages/sirval/laravel-smart-migrations)

A comprehensive Laravel package for intelligent migration management. Roll back migrations by table name or model, with explicit, safe options to prevent accidental data loss. Future versions will include analysis, validation, and optimization tools.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-smart-migrations.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-smart-migrations)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require sirval/laravel-smart-migrations
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-smart-migrations-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-smart-migrations-config"
```

This is the contents of the published config file:

```php
return [
    'model_namespace' => 'App\\Models',
    'require_confirmation' => true,
    'show_details' => true,
    'prevent_multi_batch_rollback' => true,
    'audit_log_enabled' => false,
    'audit_log_table' => 'smart_migrations_audits',
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-smart-migrations-views"
```

## Usage

### Rollback by Table

```bash
php artisan migrate:rollback-table users --latest
```

### Rollback by Model

```bash
php artisan migrate:rollback-model User --latest
```

### Programmatic Usage

```php
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

$results = SmartMigrations::rollbackTable('users', ['latest' => true]);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ohuka Ikenna](https://github.com/sirval)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
