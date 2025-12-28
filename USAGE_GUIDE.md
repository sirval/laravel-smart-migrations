# Laravel Smart Migrations - Complete Usage Guide

A comprehensive guide to using the Laravel Smart Migrations package in your projects.

## Table of Contents

1. [Installation](#installation)
2. [Quick Start](#quick-start)
3. [Available Commands](#available-commands)
4. [Programmatic API](#programmatic-api)
5. [Configuration](#configuration)
6. [Real-World Scenarios](#real-world-scenarios)
7. [Advanced Usage](#advanced-usage)
8. [Troubleshooting](#troubleshooting)
9. [Best Practices](#best-practices)
10. [FAQ](#faq)

---

## Installation

### Requirements
- PHP 8.2+
- Laravel 11.0+
- MySQL/PostgreSQL/SQLite

### Installation Steps

1. **Install via Composer**
```bash
composer require sirval/laravel-smart-migrations
```

2. **Publish Configuration** (optional, if you need to customize)
```bash
php artisan vendor:publish --provider="Sirval\LaravelSmartMigrations\LaravelSmartMigrationsServiceProvider" --tag="config"
```

3. **Verify Installation**
```bash
php artisan list | grep migrate:
```

You should see these new commands:
- `migrate:rollback-table`
- `migrate:rollback-model`
- `migrate:rollback-batch`
- `migrate:list-table-migrations`
- `migrate:list-model-migrations`

---

## Quick Start

### Example 1: Rollback the Latest Migration for a Table

```bash
php artisan migrate:rollback-table users --latest
```

This rolls back the most recent migration that modified the `users` table.

### Example 2: List All Migrations for a Table

```bash
php artisan migrate:list-table-migrations users
```

Output:
```
Migrations for table 'users':
  [1] 2024_12_01_120000_create_users_table.php (batch 1)
  [2] 2024_12_15_090000_add_email_verified_to_users_table.php (batch 2)
  [3] 2024_12_20_150000_add_two_factor_to_users_table.php (batch 3)
```

### Example 3: Rollback by Model Name

```bash
php artisan migrate:rollback-model User --latest
```

This finds the `User` model, determines its table name (`users`), and rolls back the latest migration for that table.

### Example 4: Programmatic Usage

```php
<?php

use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

// Rollback the latest migration for users table
$results = SmartMigrations::rollbackTable('users', ['latest' => true]);

// List all migrations for a table
$migrations = SmartMigrations::listMigrationsForTable('users');

// Check the status of a table
$status = SmartMigrations::getTableStatus('users');
dump($status);
```

---

## Available Commands

### 1. `migrate:rollback-table` - Rollback by Table Name

**Signature:**
```
php artisan migrate:rollback-table {table} {options}
```

**Arguments:**
- `table` (required) - The database table name (e.g., "users", "posts", "comments")

**Options:**
- `--latest` - Rollback only the most recent migration for this table
- `--oldest` - Rollback only the oldest migration for this table
- `--all` - Rollback all migrations for this table (use with caution!)
- `--batch=N` - Rollback all migrations from a specific batch number
- `--force` - Skip confirmation prompts
- `--interactive` - Show interactive menu to choose which migration(s) to rollback

**Examples:**

```bash
# Rollback the latest migration for users
php artisan migrate:rollback-table users --latest

# Rollback the oldest migration for users (useful for debugging creation issues)
php artisan migrate:rollback-table users --oldest

# Rollback all migrations for users
php artisan migrate:rollback-table users --all

# Rollback migrations from batch 3
php artisan migrate:rollback-table users --batch=3

# Non-interactive mode (no confirmation prompts)
php artisan migrate:rollback-table users --latest --force

# Interactive mode - select which migration(s) to rollback
php artisan migrate:rollback-table users --interactive
```

**Expected Output:**

When rolling back without options:
```
Error: Multiple migrations found for table 'users'. Please specify an option:

Migrations found (4):
  [1] 2024_12_20_150000_add_two_factor_to_users_table.php (batch 3)
  [2] 2024_12_15_090000_add_email_verified_to_users_table.php (batch 2)
  [3] 2024_12_01_120000_create_users_table.php (batch 1)

Available options:
  --latest          Rollback migration [1] (most recent)
  --oldest          Rollback migration [3] (oldest)
  --all             Rollback all 3 migrations (use with caution!)
  --interactive     Choose from interactive menu
  --batch=2         Rollback all migrations from batch 2
```

When using `--latest`:
```
Rolling back migration: 2024_12_20_150000_add_two_factor_to_users_table.php

Rolling back: 2024_12_20_150000_add_two_factor_to_users_table.php
✓ Rolled back successfully
```

---

### 2. `migrate:rollback-model` - Rollback by Model Name

**Signature:**
```
php artisan migrate:rollback-model {model} {options}
```

**Arguments:**
- `model` (required) - The model name or fully qualified class name
  - Can be: `User`, `App\Models\User`, `Post`, `App\Models\Blog\Post`

**Options:**
- `--latest` - Rollback only the most recent migration
- `--oldest` - Rollback only the oldest migration
- `--all` - Rollback all migrations for this model's table
- `--batch=N` - Rollback migrations from a specific batch
- `--force` - Skip confirmation prompts
- `--interactive` - Interactive menu

**Examples:**

```bash
# Using short model name (assumes App\Models namespace)
php artisan migrate:rollback-model User --latest

# Using fully qualified class name
php artisan migrate:rollback-model App\\Models\\User --latest

# Using nested model
php artisan migrate:rollback-model Blog\\Post --latest

# Interactive mode
php artisan migrate:rollback-model User --interactive
```

**Key Difference from `rollback-table`:**
- More intuitive for developers who think in terms of models
- Automatically resolves the model's table name
- Useful in applications with custom table names (e.g., User model with `protected $table = 'system_users'`)

---

### 3. `migrate:rollback-batch` - Rollback by Batch Number

**Signature:**
```
php artisan migrate:rollback-batch {batch} {options}
```

**Arguments:**
- `batch` (required) - The batch number (integer, from migrations table)

**Options:**
- `--show` - Show migrations in this batch without rolling back
- `--force` - Skip confirmation prompts

**Examples:**

```bash
# Show what's in batch 3
php artisan migrate:rollback-batch 3 --show

# Rollback all migrations from batch 3
php artisan migrate:rollback-batch 3

# Non-interactive rollback
php artisan migrate:rollback-batch 3 --force
```

**Use Cases:**
- You ran `php artisan migrate` which created batch N, and you want to undo all those migrations
- Debugging a batch of related migrations that should be deployed together

**Expected Output:**

```
Migrations in batch 3:
  [1] 2024_12_15_090000_add_email_verified_to_users_table.php
  [2] 2024_12_15_090001_add_username_to_users_table.php
  [3] 2024_12_15_090002_create_user_profiles_table.php

This action is destructive and cannot be undone. Proceed? (yes/no) [no]:
> yes

Rolling back migrations from batch 3...

Rolling back: 2024_12_15_090002_create_user_profiles_table.php
✓ Rolled back successfully

Rolling back: 2024_12_15_090001_add_username_to_users_table.php
✓ Rolled back successfully

Rolling back: 2024_12_15_090000_add_email_verified_to_users_table.php
✓ Rolled back successfully

3 migrations rolled back successfully.
```

---

### 4. `migrate:list-table-migrations` - List Migrations by Table

**Signature:**
```
php artisan migrate:list-table-migrations {table}
```

**Arguments:**
- `table` (required) - The database table name

**Examples:**

```bash
# List all migrations for users table
php artisan migrate:list-table-migrations users

# List migrations for posts table
php artisan migrate:list-table-migrations posts
```

**Expected Output:**

```
Migrations for table 'users':
┌────┬──────────────────────────────────────────────────────┬────────┐
│ #  │ Migration                                            │ Batch  │
├────┼──────────────────────────────────────────────────────┼────────┤
│ 1  │ 2024_12_01_120000_create_users_table.php             │ 1      │
│ 2  │ 2024_12_15_090000_add_email_verified_to_users_table  │ 2      │
│ 3  │ 2024_12_20_150000_add_two_factor_to_users_table.php  │ 3      │
└────┴──────────────────────────────────────────────────────┴────────┘

Total: 3 migrations
```

**Use Cases:**
- Inspect what migrations have been run for a table
- Understand the history of changes to a table
- Plan which migrations to rollback

---

### 5. `migrate:list-model-migrations` - List Migrations by Model

**Signature:**
```
php artisan migrate:list-model-migrations {model}
```

**Arguments:**
- `model` (required) - The model name or fully qualified class name

**Examples:**

```bash
# List migrations for User model
php artisan migrate:list-model-migrations User

# Using fully qualified name
php artisan migrate:list-model-migrations App\\Models\\User
```

**Expected Output:**

Same format as `migrate:list-table-migrations`, but resolved through the model.

---

## Programmatic API

### Using the Facade

```php
<?php

use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

// Rollback the latest migration for users
$results = SmartMigrations::rollbackTable('users', ['latest' => true]);

// Rollback the oldest migration
$results = SmartMigrations::rollbackTable('users', ['oldest' => true]);

// Rollback all migrations for a table
$results = SmartMigrations::rollbackTable('users', ['all' => true]);

// Rollback migrations from a specific batch
$results = SmartMigrations::rollbackBatch(3);

// Rollback by model
$results = SmartMigrations::rollbackModel('User', ['latest' => true]);

// List migrations
$migrations = SmartMigrations::listMigrationsForTable('users');
$migrations = SmartMigrations::listMigrationsForModel('User');

// Get table status
$status = SmartMigrations::getTableStatus('users');
// Returns: ['table' => 'users', 'count' => 3, 'batches' => [1, 2, 3], 'latest_batch' => 3, 'migrations' => [...]]

// Get model status
$status = SmartMigrations::getModelStatus('User');
```

### Using Dependency Injection

```php
<?php

namespace App\Services;

use Sirval\LaravelSmartMigrations\Services\SmartMigrations;

class MigrationManager
{
    public function __construct(private SmartMigrations $smartMigrations) {}

    public function rollbackUserMigrations()
    {
        return $this->smartMigrations->rollbackTable('users', ['latest' => true]);
    }
}

// In your controller or elsewhere
$manager = app(MigrationManager::class);
$manager->rollbackUserMigrations();
```

### API Methods Reference

#### `rollbackTable(string $table, array $options = []): Collection`

Rollback migrations for a specific table.

**Options:**
- `latest` (bool): Rollback only the latest migration
- `oldest` (bool): Rollback only the oldest migration
- `all` (bool): Rollback all migrations
- `batch` (int): Rollback from specific batch
- `force` (bool): Skip confirmation
- `dry_run` (bool): Show what would be rolled back without executing

**Returns:** Collection of rollback results

**Throws:** `NoMigrationsFoundException`

#### `rollbackModel(string $model, array $options = []): Collection`

Rollback migrations for a model's table.

**Options:** Same as `rollbackTable()`

**Returns:** Collection of rollback results

**Throws:** `ModelNotFoundException`, `NoMigrationsFoundException`

#### `rollbackBatch(int $batch, array $options = []): Collection`

Rollback all migrations from a specific batch.

**Options:**
- `force` (bool): Skip confirmation
- `dry_run` (bool): Show what would be rolled back

**Returns:** Collection of rollback results

**Throws:** `NoMigrationsFoundException`

#### `listMigrationsForTable(string $table): Collection`

Get all migrations for a table.

**Returns:** Collection of migration records

**Throws:** `NoMigrationsFoundException`

#### `listMigrationsForModel(string $model): Collection`

Get all migrations for a model's table.

**Returns:** Collection of migration records

**Throws:** `ModelNotFoundException`, `NoMigrationsFoundException`

#### `getTableStatus(string $table): array`

Get detailed status of a table's migrations.

**Returns:**
```php
[
    'table' => 'users',
    'count' => 3,
    'batches' => [1, 2, 3],
    'latest_batch' => 3,
    'migrations' => [/* ... */]
]
```

#### `getModelStatus(string $model): array`

Get detailed status of a model's table migrations.

**Returns:** Same format as `getTableStatus()`

---

## Configuration

### Configuration File Location

After publishing, edit `config/smart-migrations.php`:

```php
<?php

return [
    // Model namespace for resolving short model names
    // When you write: SmartMigrations::rollbackModel('User')
    // It looks for: App\Models\User
    'model_namespace' => 'App\\Models',

    // Require explicit confirmation before rolling back
    // When false, migrations roll back without prompts (careful!)
    'require_confirmation' => true,

    // Show detailed migration information before rollback
    'show_details' => true,

    // Prevent rolling back migrations from multiple batches
    // When true, you must add --force to rollback across batches
    'prevent_multi_batch_rollback' => true,

    // Enable audit logging to track who rolled back what
    'audit_log_enabled' => false,
    'audit_log_table' => 'smart_migrations_audits',
];
```

### Configuration Options Explained

#### `model_namespace`

**Default:** `'App\\Models'`

Used when you pass a short model name to rollback commands.

```bash
# If model_namespace is 'App\Models':
php artisan migrate:rollback-model User
# Looks for: App\Models\User

# If model_namespace is 'App\Domain\Models':
php artisan migrate:rollback-model User
# Looks for: App\Domain\Models\User

# You can always use the full path to bypass this:
php artisan migrate:rollback-model 'App\\Different\\Namespace\\User'
```

#### `require_confirmation`

**Default:** `true`

When enabled, all rollback operations require confirmation before proceeding.

```bash
# With require_confirmation = true:
php artisan migrate:rollback-table users --latest
# Output: "This action is destructive and cannot be undone. Proceed? (yes/no)"

# With require_confirmation = false:
# Rollback happens immediately without prompting
# Use --force to suppress confirmations even when enabled
```

#### `show_details`

**Default:** `true`

Display detailed information about migrations before rollback.

```bash
# With show_details = true:
# Shows full SQL schema information for the migration

# With show_details = false:
# Only shows migration filename and confirmation prompt
```

#### `prevent_multi_batch_rollback`

**Default:** `true`

Prevent rolling back migrations from different batches without explicit `--force`.

```bash
# With prevent_multi_batch_rollback = true:
php artisan migrate:rollback-table users --all
# Error: Cannot rollback migrations from multiple batches without --force

php artisan migrate:rollback-table users --all --force
# Proceeds

# With prevent_multi_batch_rollback = false:
# Allows rolling back across batches without --force
```

#### `audit_log_enabled`

**Default:** `false`

Log all rollback operations for audit purposes.

When enabled, every rollback creates a record in `smart_migrations_audits` table with:
- User who performed rollback
- Table/model affected
- Migrations rolled back
- Timestamp
- Dry run status

---

## Real-World Scenarios

### Scenario 1: You Made a Mistake in the Latest Migration

**Situation:** You ran a migration that adds a column with the wrong type, and you need to fix it.

**Steps:**

```bash
# 1. List all migrations for the affected table
php artisan migrate:list-table-migrations users

# Output shows:
# - 2024_12_20_150000_add_phone_to_users_table.php (batch 3) <- This is the problem

# 2. Rollback the latest migration
php artisan migrate:rollback-table users --latest

# 3. Fix the migration file (update the type or logic)

# 4. Re-run migrations
php artisan migrate

# Done!
```

---

### Scenario 2: You Need to Rollback Multiple Related Migrations

**Situation:** You have a feature that spans 3 migrations across 2 tables. You want to rollback all of them as a group.

**Approach 1: By Batch (Recommended)**

```bash
# Check which batch your migrations are in
php artisan migrate:list-table-migrations users
php artisan migrate:list-table-migrations posts

# If they're in the same batch:
php artisan migrate:rollback-batch 5 --show

# Then rollback the entire batch
php artisan migrate:rollback-batch 5
```

**Approach 2: By Model/Table**

```bash
# Rollback both tables individually
php artisan migrate:rollback-model User --all
php artisan migrate:rollback-model Post --all
```

---

### Scenario 3: Debugging Production Issues

**Situation:** A migration on production caused issues. You need to safely rollback just that migration and understand what happened.

```bash
# 1. Get the exact migration list
php artisan migrate:list-table-migrations users

# 2. Examine which migration caused the issue
# Let's say it's: 2024_12_20_150000_add_two_factor_to_users_table.php

# 3. Rollback only that migration (use --latest if it's the most recent)
php artisan migrate:rollback-table users --latest

# 4. Monitor application behavior
# Check logs, user reports, database state

# 5. Once confirmed safe, deploy the fix
# Either in a new migration or by rolling back further if needed
```

---

### Scenario 4: Running Migrations in CI/CD Pipeline

**Situation:** You want to test rollback mechanisms in your CI/CD tests.

```php
<?php

// In your test or seeder
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

// Test that rollback works
$migrations = SmartMigrations::listMigrationsForTable('users');
$this->assertGreaterThan(0, $migrations->count());

// Rollback latest
$results = SmartMigrations::rollbackTable('users', ['latest' => true, 'force' => true]);
$this->assertNotEmpty($results);

// Verify table still exists but with rolled back state
$columns = Schema::getColumnListing('users');
$this->assertFalse(in_array('two_factor_secret', $columns));

// Re-migrate to restore state
Artisan::call('migrate');
```

---

### Scenario 5: Programmatic Usage in Job

**Situation:** You have a queue job that needs to intelligently handle migrations.

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;
use Sirval\LaravelSmartMigrations\Exceptions\NoMigrationsFoundException;

class RollbackTableMigrations implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $table) {}

    public function handle()
    {
        try {
            $status = SmartMigrations::getTableStatus($this->table);

            if ($status['count'] === 0) {
                $this->fail("No migrations found for table: {$this->table}");
                return;
            }

            // Rollback oldest first (safest approach)
            $results = SmartMigrations::rollbackTable(
                $this->table,
                ['oldest' => true, 'force' => true]
            );

            $this->log("Successfully rolled back {$results->count()} migrations");
        } catch (NoMigrationsFoundException $e) {
            $this->fail($e->getMessage());
        }
    }

    private function log(string $message)
    {
        \Log::info("Migration Rollback Job: {$message}");
    }
}

// In your controller or command:
dispatch(new RollbackTableMigrations('users'));
```

---

## Advanced Usage

### Custom Model Resolution

If your models are in a custom namespace:

```php
// config/smart-migrations.php
'model_namespace' => 'App\\Domain\\Models',

// Now this works:
php artisan migrate:rollback-model User --latest
// Looks for: App\Domain\Models\User
```

### Dry-Run Mode (Programmatic Only)

Preview what would be rolled back without executing:

```php
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

// See what would be rolled back
$dryRun = SmartMigrations::rollbackTable('users', ['latest' => true, 'dry_run' => true]);

foreach ($dryRun as $migration) {
    echo "Would rollback: {$migration['migration']}\n";
}

// Nothing was actually rolled back
```

### Handling Exceptions in Code

```php
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;
use Sirval\LaravelSmartMigrations\Exceptions\NoMigrationsFoundException;
use Sirval\LaravelSmartMigrations\Exceptions\ModelNotFoundException;

try {
    $results = SmartMigrations::rollbackTable('users', ['latest' => true]);
} catch (NoMigrationsFoundException $e) {
    // Handle: no migrations found
    Log::warning("No migrations to rollback: " . $e->getMessage());
} catch (ModelNotFoundException $e) {
    // Handle: model doesn't exist
    Log::error("Model not found: " . $e->getMessage());
} catch (\Exception $e) {
    // Handle: other errors
    Log::error("Unexpected error: " . $e->getMessage());
}
```

### Using with Custom Database Connections

The package uses your default database connection. To use a specific connection:

```php
// You would need to extend the services or use Laravel's DB facade:
use Illuminate\Support\Facades\DB;

// Switch connection for a specific query
DB::connection('mysql_secondary')->statement(/* ... */);
```

---

## Troubleshooting

### Issue: "No migrations found for table"

**Cause:** The table name might be different from what you expect.

**Solution:**

```bash
# Check all migrations to find the correct table
php artisan migrate:status

# Or query the database directly
php artisan tinker
>>> DB::table('migrations')->get();

# Then use the exact table name
php artisan migrate:rollback-table exact_table_name --latest
```

### Issue: "Model not found"

**Cause:** Model is not in the configured namespace.

**Solution:**

```bash
# Option 1: Use the full namespace
php artisan migrate:rollback-model 'App\\Custom\\Namespace\\User' --latest

# Option 2: Update config
# Edit config/smart-migrations.php:
'model_namespace' => 'App\\Custom\\Namespace',

# Then use short name
php artisan migrate:rollback-model User --latest
```

### Issue: "Multiple migrations found" but no options shown

**Cause:** You need to specify which migration(s) to rollback.

**Solution:**

```bash
# Use one of these options:
php artisan migrate:rollback-table users --latest    # Most recent
php artisan migrate:rollback-table users --oldest    # Oldest
php artisan migrate:rollback-table users --all       # All
php artisan migrate:rollback-table users --batch=2  # Specific batch
php artisan migrate:rollback-table users --interactive # Choose from menu
```

### Issue: Rollback is waiting for confirmation but you need non-interactive mode

**Solution:**

```bash
# Use --force to skip confirmation
php artisan migrate:rollback-table users --latest --force

# Or disable confirmation in config
# config/smart-migrations.php:
'require_confirmation' => false,
```

### Issue: Can't rollback across multiple batches

**Cause:** Safety feature to prevent accidental multi-batch rollbacks.

**Solution:**

```bash
# Use --force to override the safety check
php artisan migrate:rollback-table users --all --force

# Or disable the check in config
# config/smart-migrations.php:
'prevent_multi_batch_rollback' => false,
```

### Issue: Table still exists in database after rollback

**Cause:** This is normal behavior. When a migration is rolled back, the migration record is removed from the `migrations` table, but the actual database table is NOT automatically dropped. This happens because Laravel's `down()` method must explicitly drop the table.

**Why This Happens:**

The rollback removes the **migration history record**, not the **database table**. Example:

```
Before rollback:
  - Table 'login_approvals' exists in database ✓
  - Migration '2025_12_28_022743_create_login_approvals_table' in migrations table ✓

After rollback:
  - Table 'login_approvals' exists in database ✓ (still here!)
  - Migration record removed from migrations table ✗ (gone)

Result: Orphaned table (exists in DB but no migration record)
```

**Solution:**

See the complete guide: **[DATABASE_CLEANUP_GUIDE.md](./DATABASE_CLEANUP_GUIDE.md)**

Quick options:

```bash
# Option 1: Drop the table immediately (if you don't need the data)
php artisan tinker
>>> \Schema::dropIfExists('login_approvals');

# Option 2: Create a cleanup migration (recommended)
php artisan make:migration drop_login_approvals_table
# Edit the migration to include: Schema::dropIfExists('login_approvals');
# Then run: php artisan migrate

# Option 3: Drop via raw SQL (database-specific)
# MySQL: DROP TABLE IF EXISTS login_approvals;
# PostgreSQL: DROP TABLE IF EXISTS login_approvals CASCADE;
# SQLite: DROP TABLE IF EXISTS login_approvals;
```

**Prevention:**

Always ensure your migrations have proper `down()` methods:

```php
public function down(): void
{
    Schema::dropIfExists('login_approvals');  // Don't forget this!
}
```

---

## Best Practices

### 1. Always List Before Rolling Back

```bash
# GOOD: Check what exists first
php artisan migrate:list-table-migrations users
php artisan migrate:rollback-table users --latest

# BAD: Blindly rollback without checking
php artisan migrate:rollback-table users --all --force
```

### 2. Use Specific Options, Not `--all`

```bash
# GOOD: Specific, intentional
php artisan migrate:rollback-table users --latest

# LESS GOOD: Rolls back everything at once
php artisan migrate:rollback-table users --all
```

### 3. Test in Development First

```bash
# If unsure about a rollback:
# 1. Test locally first
# 2. Use dry-run mode to preview
# 3. Then execute in production
```

### 4. Use Batches for Related Migrations

When running `php artisan migrate`, all migrations in one execution are in the same batch. Use this to your advantage:

```bash
# All these migrations created in one `migrate` command are in the same batch
php artisan migrate

# Later, rollback all of them as a unit
php artisan migrate:rollback-batch 5
```

### 5. Document Complex Rollback Procedures

```bash
# BAD: Undocumented steps
php artisan migrate:rollback-model Order --all --force

# GOOD: Documented reasoning
# Note: Rolling back Order migrations to revert feature X
# Reason: Feature X caused data inconsistency
# Rollback date: 2024-12-20
php artisan migrate:rollback-model Order --all --force
```

### 6. Keep Audit Logs Enabled in Production

```php
// config/smart-migrations.php
'audit_log_enabled' => env('AUDIT_MIGRATIONS', false),

// In production .env:
AUDIT_MIGRATIONS=true
```

### 7. Use Programmatic API for Automation

```php
// BAD: Shell commands in code
shell_exec('php artisan migrate:rollback-table users --latest --force');

// GOOD: Use the API directly
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

SmartMigrations::rollbackTable('users', ['latest' => true, 'force' => true]);
```

---

## FAQ

### Q: Can I rollback migrations from different tables at once?

**A:** Not directly with a single command. Use the programmatic API or run multiple commands:

```php
// Programmatic approach
SmartMigrations::rollbackTable('users', ['all' => true]);
SmartMigrations::rollbackTable('posts', ['all' => true]);
```

### Q: What happens to data when I rollback?

**A:** The `down()` method in your migration is executed. This varies by migration:
- `dropTable()` - Deletes all table data
- `dropColumn()` - Removes the column and its data
- `rollback()` - Custom logic you defined

**Always backup first in production!**

### Q: Can I rollback a migration that doesn't exist anymore?

**A:** No. The migration must still be present in your `migrations` directory for the framework to execute its `down()` method.

### Q: How do I find the batch number?

**A:** Check the `migrations` table or use the list commands:

```php
// Direct query
DB::table('migrations')->get();

// Or use the package
SmartMigrations::getTableStatus('users');
// Returns array with 'batches' => [1, 2, 3]
```

### Q: Can I use this in tests?

**A:** Yes! Useful for resetting state:

```php
// In your test setUp
protected function setUp(): void
{
    parent::setUp();

    // Ensure clean state
    SmartMigrations::rollbackTable('users', ['all' => true]);
    Artisan::call('migrate');
}
```

### Q: Is it safe to use in production?

**A:** Yes, with precautions:
1. Always use `--show` first to verify
2. Test in staging first
3. Have database backups
4. Use specific options (`--latest`, not `--all`)
5. Consider scheduling during maintenance windows

### Q: What if a migration fails to rollback?

**A:** The package catches exceptions and reports them. The migration record remains in the database. You may need to:
1. Fix the migration's `down()` method
2. Manually clean up database state
3. Update the migrations table if needed

---

## Next Steps

- [Read the Architecture Guide](./ARCHITECTURE.md)
- [View API Reference](./API_REFERENCE.md)
- [See Contributing Guide](./CONTRIBUTING.md)
- [Check Examples](./EXAMPLES.md)

---

**Need Help?**

- GitHub Issues: [Create an issue](https://github.com/sirval/laravel-smart-migrations/issues)
- Documentation: [README.md](../README.md)
- Email: ohukaiv@gmail.com
