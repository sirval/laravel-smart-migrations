# Laravel Smart Migrations

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sirval/laravel-smart-migrations.svg?style=flat-square)](https://packagist.org/packages/sirval/laravel-smart-migrations)
[![GitHub Tests](https://github.com/sirval/laravel-smart-migrations/workflows/Tests/badge.svg)](https://github.com/sirval/laravel-smart-migrations)
[![Total Downloads](https://img.shields.io/packagist/dt/sirval/laravel-smart-migrations.svg?style=flat-square)](https://packagist.org/packages/sirval/laravel-smart-migrations)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-brightgreen.svg?style=flat-square)](https://www.php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%3E%3D11.0-brightgreen.svg?style=flat-square)](https://laravel.com)

Intelligently manage Laravel migrations with safe rollback by table name or model. This package provides powerful commands and a clean API for rolling back, listing, and analyzing database migrations.

## Features

✨ **Rollback by Table or Model** - Rollback migrations using table names or model classes
🛡️ **Safety First** - Confirmation prompts, batch awareness, and detailed validation
📊 **List & Analyze** - Inspect migration history for any table or model
🎯 **Multiple Strategies** - Rollback latest, oldest, all, or specific batch
⚙️ **Programmatic API** - Use it in code, jobs, events, and anywhere else
🔧 **Highly Configurable** - Customize confirmation, batch safety, model namespaces
📚 **Well Documented** - 310+ pages of comprehensive documentation with examples

## Requirements

- PHP 8.2+
- Laravel 11.0+
- MySQL, PostgreSQL, SQLite, or any Laravel-supported database

## Installation

Install the package via Composer:

```bash
composer require sirval/laravel-smart-migrations
```

That's it! The package auto-registers via service provider discovery.

## Quick Start

### List migrations for a table

```bash
php artisan migrate:list-table-migrations users
```

Output:
```
Migrations for table 'users':
┌────┬──────────────────────────────────────────────┬────────┐
│ #  │ Migration                                    │ Batch  │
├────┼──────────────────────────────────────────────┼────────┤
│ 1  │ 2024_12_01_120000_create_users_table         │ 1      │
│ 2  │ 2024_12_15_090000_add_email_verified_...    │ 2      │
└────┴──────────────────────────────────────────────┴────────┘
```

### Rollback the latest migration

```bash
php artisan migrate:rollback-table users --latest
```

Output:
```
Rolling back migration: 2024_12_15_090000_add_email_verified_to_users_table.php

Rolling back: 2024_12_15_090000_add_email_verified_to_users_table.php
✓ Rolled back successfully
```

### Rollback by model

```bash
php artisan migrate:rollback-model User --latest
```

### Programmatic usage

```php
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

// Rollback latest migration for users
$results = SmartMigrations::rollbackTable('users', ['latest' => true]);

// List all migrations for a model
$migrations = SmartMigrations::listMigrationsForModel('User');

// Get detailed table status
$status = SmartMigrations::getTableStatus('users');
// Returns: ['table' => 'users', 'count' => 3, 'batches' => [1, 2, 3], ...]
```

## Available Commands

### 1. `migrate:rollback-table` - Rollback by table name
```bash
php artisan migrate:rollback-table {table} {--latest|--oldest|--all|--batch=|--force|--interactive}
```

### 2. `migrate:rollback-model` - Rollback by model
```bash
php artisan migrate:rollback-model {model} {--latest|--oldest|--all|--force|--interactive}
```

### 3. `migrate:rollback-batch` - Rollback by batch
```bash
php artisan migrate:rollback-batch {batch} {--show|--force}
```

### 4. `migrate:list-table-migrations` - List table migrations
```bash
php artisan migrate:list-table-migrations {table}
```

### 5. `migrate:list-model-migrations` - List model migrations
```bash
php artisan migrate:list-model-migrations {model}
```

See [USAGE_GUIDE.md → Available Commands](./USAGE_GUIDE.md#available-commands) for detailed documentation of each command.

## Configuration

Publish the config file (optional):

```bash
php artisan vendor:publish --provider="Sirval\LaravelSmartMigrations\LaravelSmartMigrationsServiceProvider" --tag="config"
```

Then edit `config/smart-migrations.php`:

```php
return [
    // Model namespace (when using short names like "User")
    'model_namespace' => 'App\\Models',

    // Require confirmation before rollback
    'require_confirmation' => true,

    // Show migration details before rollback
    'show_details' => true,

    // Prevent rolling back migrations from different batches
    'prevent_multi_batch_rollback' => true,

    // Enable audit logging
    'audit_log_enabled' => false,
    'audit_log_table' => 'smart_migrations_audits',
];
```

See [USAGE_GUIDE.md → Configuration](./USAGE_GUIDE.md#configuration) for detailed configuration options.

## API Reference

### SmartMigrations Facade

```php
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

// Rollback migrations
SmartMigrations::rollbackTable(string $table, array $options = []): Collection
SmartMigrations::rollbackModel(string $model, array $options = []): Collection
SmartMigrations::rollbackBatch(int $batch, array $options = []): Collection

// List migrations
SmartMigrations::listMigrationsForTable(string $table): Collection
SmartMigrations::listMigrationsForModel(string $model): Collection

// Get status
SmartMigrations::getTableStatus(string $table): array
SmartMigrations::getModelStatus(string $model): array
```

**Options for rollback methods:**
- `latest` (bool) - Rollback only the latest migration
- `oldest` (bool) - Rollback only the oldest migration
- `all` (bool) - Rollback all migrations
- `batch` (int) - Rollback all migrations from specific batch
- `force` (bool) - Skip confirmation prompts
- `dry_run` (bool) - Preview without executing

See [USAGE_GUIDE.md → Programmatic API](./USAGE_GUIDE.md#programmatic-api) for complete API documentation.

## Real-World Examples

### Example 1: Quick Reset During Development

```bash
php artisan migrate:rollback-table posts --latest
# Fix your migration file
php artisan migrate
```

### Example 2: Rollback Multiple Related Migrations

```bash
# List migrations to see batch numbers
php artisan migrate:list-table-migrations users
php artisan migrate:list-table-migrations posts

# Rollback entire batch
php artisan migrate:rollback-batch 5
```

### Example 3: Programmatic Rollback in Job

```php
<?php

use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

class RollbackProblematicMigration
{
    public function handle()
    {
        try {
            SmartMigrations::rollbackTable('users', [
                'latest' => true,
                'force' => true,
            ]);
            
            Log::info('Rolled back user migrations');
        } catch (\Exception $e) {
            Log::error("Rollback failed: {$e->getMessage()}");
        }
    }
}
```

See [CONTRIBUTING_AND_EXAMPLES.md](./CONTRIBUTING_AND_EXAMPLES.md) for 6 complete real-world examples.

## Documentation

We provide comprehensive documentation for different audiences:

### For Users
📖 **[USAGE_GUIDE.md](./USAGE_GUIDE.md)** (55+ pages)
- Installation & setup
- Command reference
- API reference
- Configuration guide
- Real-world scenarios
- Troubleshooting
- FAQ

### For Developers
🏗️ **[ARCHITECTURE_GUIDE.md](./ARCHITECTURE_GUIDE.md)** (70+ pages)
- Architecture overview
- Service layer design
- Design patterns
- How to extend the package
- Security considerations
- Performance tips

📋 **[TESTING_GUIDE.md](./TESTING_GUIDE.md)** (60+ pages)
- Testing overview
- Unit test examples
- Feature test examples
- Writing custom tests
- CI/CD integration

### For Contributors
🤝 **[CONTRIBUTING_AND_EXAMPLES.md](./CONTRIBUTING_AND_EXAMPLES.md)** (50+ pages)
- Contributing workflow
- Code standards
- 6 real-world examples
- Troubleshooting scenarios
- Development setup

### Navigation
🗂️ **[DOCUMENTATION_INDEX.md](./DOCUMENTATION_INDEX.md)** - Quick navigation guide

---

## Safety Features

✅ **Confirmation Prompts** - Requires explicit confirmation before rolling back (configurable)
✅ **Batch Awareness** - Prevents accidental multi-batch rollbacks
✅ **Detailed Display** - Shows which migrations will be affected
✅ **Error Handling** - Custom exceptions with helpful messages
✅ **Dry-Run Mode** - Preview rollbacks without executing
✅ **Transaction Support** - Leverages Laravel's database transactions

## Testing

The package includes comprehensive tests with 18+ test cases covering all commands, services, and edge cases.

Run tests:
```bash
./vendor/bin/pest
```

View coverage:
```bash
./vendor/bin/pest --coverage
```

## Troubleshooting

### "Multiple migrations found" error

When multiple migrations exist for a table, you must specify which to rollback:

```bash
# Rollback latest
php artisan migrate:rollback-table users --latest

# Rollback oldest
php artisan migrate:rollback-table users --oldest

# Rollback all (use with caution!)
php artisan migrate:rollback-table users --all

# Choose from interactive menu
php artisan migrate:rollback-table users --interactive
```

### Model not found

Ensure your model is in the correct namespace:

```bash
# If your model is in custom namespace, use full path
php artisan migrate:rollback-model App\\Domain\\Models\\User --latest

# Or configure the namespace in config/smart-migrations.php
```

See [USAGE_GUIDE.md → Troubleshooting](./USAGE_GUIDE.md#troubleshooting) for more common issues and solutions.

## Best Practices

1. **Always list before rolling back**
   ```bash
   php artisan migrate:list-table-migrations users
   php artisan migrate:rollback-table users --latest
   ```

2. **Test in development first** - Don't rollback in production without testing

3. **Use specific options** - Use `--latest` instead of `--all` when possible

4. **Backup before production rollbacks** - Always have a database backup

5. **Document your rollbacks** - Add comments explaining why

6. **Use batch rollbacks for related migrations** - If migrations are in same batch, rollback together

7. **Monitor with health checks** - Use programmatic API to verify database state

See [USAGE_GUIDE.md → Best Practices](./USAGE_GUIDE.md#best-practices) for detailed recommendations.

## FAQ

**Q: Can I rollback migrations from different tables?**
A: Use the programmatic API to rollback multiple tables or run multiple commands.

**Q: What happens to my data when I rollback?**
A: The `down()` method of your migration is executed. This varies by migration (dropTable, dropColumn, custom logic).

**Q: Is it safe to use in production?**
A: Yes, with precautions. Always backup first, test in staging, and use specific options.

See [USAGE_GUIDE.md → FAQ](./USAGE_GUIDE.md#faq) for more questions.

## Contributing

We welcome contributions! Please see [CONTRIBUTING_AND_EXAMPLES.md](./CONTRIBUTING_AND_EXAMPLES.md) for guidelines.

### Quick Contribution Steps

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Write tests
4. Make your changes
5. Run tests and code quality checks
6. Submit a pull request

Code should follow:
- PSR-12 coding standard
- Include type hints
- Include comprehensive tests
- Include documentation

## License

The MIT License (MIT). See [LICENSE.md](./LICENSE.md) for more details.

## Changelog

See [CHANGELOG.md](./CHANGELOG.md) for version history and upgrade guides.

## Support

- 📖 **Documentation**: [See docs](./USAGE_GUIDE.md)
- 🐛 **Issues**: [GitHub Issues](https://github.com/sirval/laravel-smart-migrations/issues)
- 📧 **Email**: ohukaiv@gmail.com
- 💬 **Discussions**: [GitHub Discussions](https://github.com/sirval/laravel-smart-migrations/discussions)

## Project Status

✅ **Version 1.0.0** - Production Ready

**Included:**
- ✅ 5 powerful commands
- ✅ Clean programmatic API
- ✅ Flexible configuration
- ✅ 310+ pages of documentation
- ✅ 110+ code examples
- ✅ 18+ passing tests

---

**Made with ❤️ by [Ohuka Ikenna](https://github.com/sirval)**

Give it a ⭐ if you find it useful!
