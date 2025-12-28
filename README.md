# Laravel Smart Migrations

Intelligently manage Laravel migrations with safe rollback, analysis, validation, and comprehensive tools for table and model-based operations.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sirval/laravel-smart-migrations.svg?style=flat-square)](https://packagist.org/packages/sirval/laravel-smart-migrations)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/sirval/laravel-smart-migrations/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/sirval/laravel-smart-migrations/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/sirval/laravel-smart-migrations/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/sirval/laravel-smart-migrations/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/sirval/laravel-smart-migrations.svg?style=flat-square)](https://packagist.org/packages/sirval/laravel-smart-migrations)

A comprehensive Laravel package for intelligent migration management. Roll back migrations by table name or model, with explicit, safe options to prevent accidental data loss. Future versions will include analysis, validation, and optimization tools.

## ⭐ Give us a Star

If you find this package helpful, please consider giving us a star on GitHub! It helps us continue maintaining and improving this package. Your support means a lot to us! ☕

[⭐ Star us on GitHub](https://github.com/sirval/laravel-smart-migrations)

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

### Available Commands

#### 1. Rollback by Table Name

Roll back all migrations for a specific table:

```bash
# Rollback latest migration only
php artisan migrate:rollback-table users --latest
php artisan migrate:rollback-table users -L

# Rollback oldest migration only
php artisan migrate:rollback-table users --oldest
php artisan migrate:rollback-table users -O

# Rollback all migrations for this table
php artisan migrate:rollback-table users --all
php artisan migrate:rollback-table users -A

# Rollback migrations from specific batch
php artisan migrate:rollback-table users --batch=5
php artisan migrate:rollback-table users -B 5

# Skip confirmation prompt
php artisan migrate:rollback-table users --force
php artisan migrate:rollback-table users -F

# Interactive mode (choose from available options)
php artisan migrate:rollback-table users --interactive
php artisan migrate:rollback-table users -I

# Preview the changes without executing
php artisan migrate:rollback-table users --preview

# Check for foreign key constraints
php artisan migrate:rollback-table users --check-fk

# Combine options: preview with foreign key check
php artisan migrate:rollback-table users --preview --check-fk

# Rollback multiple tables at once
php artisan migrate:rollback-table users,posts,comments --latest
php artisan migrate:rollback-table users,posts,comments -L
php artisan migrate:rollback-table users posts comments --oldest
php artisan migrate:rollback-table users posts comments -O
```

#### 2. Rollback by Model Name

Roll back all migrations associated with a specific model:

```bash
# Rollback latest migration
php artisan migrate:rollback-model User --latest
php artisan migrate:rollback-model User -L

# Rollback oldest migration
php artisan migrate:rollback-model User --oldest
php artisan migrate:rollback-model User -O

# Rollback all migrations
php artisan migrate:rollback-model User --all
php artisan migrate:rollback-model User -A

# Rollback by batch
php artisan migrate:rollback-model User --batch=5
php artisan migrate:rollback-model User -B 5

# Skip confirmation
php artisan migrate:rollback-model User --force
php artisan migrate:rollback-model User -F

# Interactive mode
php artisan migrate:rollback-model User --interactive
php artisan migrate:rollback-model User -I

# Preview the changes without executing
php artisan migrate:rollback-model User --preview

# Check for foreign key constraints
php artisan migrate:rollback-model User --check-fk

# Combine options: preview with foreign key check
php artisan migrate:rollback-model User --preview --check-fk

# Rollback multiple models at once
php artisan migrate:rollback-model User,Post,Comment --latest
php artisan migrate:rollback-model User,Post,Comment -L
php artisan migrate:rollback-model User Post Comment --oldest
php artisan migrate:rollback-model User Post Comment -O
```

#### 3. Rollback by Batch Number

Roll back all migrations from a specific batch:

```bash
# Rollback all migrations from batch 5
php artisan migrate:rollback-batch 5

# Skip confirmation
php artisan migrate:rollback-batch 5 --force
php artisan migrate:rollback-batch 5 -F
```

#### 4. List Migrations for Table

View all migrations affecting a specific table:

```bash
php artisan migrate:list-table-migrations users
```

#### 5. List Migrations for Model

View all migrations associated with a specific model:

```bash
php artisan migrate:list-model-migrations User
```

### Command Options

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--latest` | `-L` | Only rollback the latest migration | `false` |
| `--oldest` | `-O` | Only rollback the oldest migration | `false` |
| `--all` | `-A` | Rollback all migrations (ignore batch restrictions) | `false` |
| `--batch=N` | `-B` | Only rollback migrations from batch N | `null` |
| `--force` | `-F` | Skip confirmation prompts | `false` |
| `--interactive` | `-I` | Show options and let user choose | `false` |
| `--preview` | — | Preview changes without executing rollback | `false` |
| `--check-fk` | — | Check and display foreign key constraints | `false` |

### Configuration

The package respects these configuration options from `config/smart-migrations.php`:

```php
return [
    // Default model namespace for model resolution
    'model_namespace' => 'App\\Models',
    
    // Require user confirmation before rollback
    'require_confirmation' => true,
    
    // Show detailed migration information
    'show_details' => true,
    
    // Prevent rolling back multiple batches at once
    'prevent_multi_batch_rollback' => true,
    
    // Enable audit logging for rollbacks
    'audit_log_enabled' => false,
    
    // Table name for audit logs
    'audit_log_table' => 'smart_migrations_audits',
];
```

### Programmatic Usage

Use the SmartMigrations facade for programmatic access:

```php
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

// Rollback by table
$results = SmartMigrations::rollbackTable('users', ['latest' => true]);

// Rollback by model
$results = SmartMigrations::rollbackModel('User', ['latest' => true]);

// Rollback by batch
$results = SmartMigrations::rollbackBatch(5);

// List migrations for table
$migrations = SmartMigrations::listMigrationsForTable('users');

// List migrations for model
$migrations = SmartMigrations::listMigrationsForModel('User');

// Get table status
$status = SmartMigrations::getTableStatus('users');

// Get model status
$status = SmartMigrations::getModelStatus('User');
```

### Available Options for Rollback Methods

When calling rollback methods programmatically, pass options as an array:

```php
$options = [
    'latest' => true,      // Rollback latest only
    'oldest' => false,     // Rollback oldest only
    'all' => false,        // Rollback all
    'batch' => null,       // Specific batch number
    'force' => false,      // Skip confirmation
    'dry_run' => false,    // Preview without executing
];

SmartMigrations::rollbackTable('users', $options);
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
