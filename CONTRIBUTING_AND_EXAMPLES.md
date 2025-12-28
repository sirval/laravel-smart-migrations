# Contributing & Examples Guide

How to contribute to Laravel Smart Migrations and real-world usage examples.

## Table of Contents

1. [Contributing](#contributing)
2. [Code Standards](#code-standards)
3. [Real-World Examples](#real-world-examples)
4. [Common Use Cases](#common-use-cases)
5. [Troubleshooting Scenarios](#troubleshooting-scenarios)
6. [Development Setup](#development-setup)

---

## Contributing

### Getting Started

1. **Fork the repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/laravel-smart-migrations.git
   cd laravel-smart-migrations
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

4. **Make your changes**
   ```bash
   # Write code
   # Add tests
   # Update documentation
   ```

5. **Run tests**
   ```bash
   ./vendor/bin/pest
   ```

6. **Commit and push**
   ```bash
   git add .
   git commit -m "feat: describe your feature"
   git push origin feature/your-feature-name
   ```

7. **Create a Pull Request**
   - Go to GitHub
   - Create PR with clear description
   - Reference any related issues

### Contribution Types

#### Bug Fixes
```bash
git checkout -b fix/issue-description

# Fix the bug
# Add regression test
# Update changelog
```

#### Features
```bash
git checkout -b feature/new-feature

# Implement feature
# Add feature tests
# Update documentation
```

#### Documentation
```bash
git checkout -b docs/improve-readme

# Update documentation
# Add examples
# Fix typos
```

#### Tests
```bash
git checkout -b test/improve-coverage

# Add more test cases
# Improve test coverage
# Add edge case tests
```

---

## Code Standards

### PHP Code Style

Follow PSR-12 standard:

```php
<?php
// Good
namespace Sirval\LaravelSmartMigrations;

class MyClass
{
    public function myMethod(string $name): string
    {
        return $name;
    }
}

// Bad
namespace Sirval\LaravelSmartMigrations;

class MyClass {
    public function myMethod($name) {
        return $name;
    }
}
```

### Type Hints

Always use type hints:

```php
// Good
public function rollbackTable(string $table, array $options = []): Collection
{
    // Implementation
}

// Bad
public function rollbackTable($table, $options = [])
{
    // Implementation
}
```

### Documentation

Add PHPDoc comments:

```php
<?php

/**
 * Rollback migrations for a specific table.
 *
 * @param string $table The database table name
 * @param array $options Rollback options (latest, oldest, all, batch, force)
 *
 * @return Collection Results of rollback operations
 *
 * @throws NoMigrationsFoundException
 *
 * @example
 * $results = SmartMigrations::rollbackTable('users', ['latest' => true]);
 */
public function rollbackTable(string $table, array $options = []): Collection
{
    // Implementation
}
```

### Testing Requirements

- Write tests for new features
- Maintain 90%+ code coverage
- Test happy path and error cases
- Use descriptive test names

```php
<?php

// Good test names
it('rolls back latest migration for table', function () { });
it('throws exception when no migrations found', function () { });
it('requires confirmation before rollback', function () { });

// Bad test names
it('tests rollback', function () { });
it('migration test', function () { });
```

### Static Analysis

Run PHPStan:

```bash
./vendor/bin/phpstan analyse src/
```

Target: Level 8

```php
// Good - Fully typed
public function findByTable(string $table): Collection
{
    return DB::table('migrations')
        ->where('migration', 'like', "%{$table}%")
        ->get();
}

// Bad - Missing types
public function findByTable($table)
{
    return DB::table('migrations')->where('migration', 'like', "%{$table}%")->get();
}
```

### Commit Messages

Use conventional commits:

```bash
# Good
git commit -m "feat: add dry-run mode for rollback commands"
git commit -m "fix: prevent rolling back from multiple batches"
git commit -m "docs: update usage guide with examples"
git commit -m "test: add tests for ModelResolver"

# Bad
git commit -m "updates"
git commit -m "fix stuff"
git commit -m "WIP"
```

### Pull Request Checklist

Before submitting a PR:

- [ ] Tests added/updated
- [ ] All tests pass (`./vendor/bin/pest`)
- [ ] Code follows style (`./vendor/bin/pint`)
- [ ] PHPStan passes (`./vendor/bin/phpstan`)
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] No breaking changes (or documented)
- [ ] Commit messages are clear

---

## Real-World Examples

### Example 1: Scheduled Database Cleanup

**Scenario:** Clean up temporary tables created during testing.

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

class CleanupTestTables extends Command
{
    protected $signature = 'db:cleanup-test-tables';
    protected $description = 'Remove all test tables from database';

    public function handle()
    {
        $testTables = [
            'test_users',
            'test_posts',
            'test_comments',
        ];

        foreach ($testTables as $table) {
            try {
                // List migrations for this table
                $migrations = SmartMigrations::listMigrationsForTable($table);

                $this->info("Rolling back {$migrations->count()} migrations for '{$table}'");

                // Rollback all
                $results = SmartMigrations::rollbackTable($table, [
                    'all' => true,
                    'force' => true,
                ]);

                $this->line("✓ Cleaned up {$table}");
            } catch (\Exception $e) {
                $this->warn("Could not clean up {$table}: {$e->getMessage()}");
            }
        }

        $this->info('Done!');
    }
}
```

**Usage:**
```bash
php artisan db:cleanup-test-tables
```

---

### Example 2: Automated Rollback with Notifications

**Scenario:** Automatically rollback problematic migrations and notify the team.

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;
use Slack\Notification as SlackNotification;

class RollbackProblematicMigration implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $table,
        private string $reason,
    ) {}

    public function handle()
    {
        try {
            $status = SmartMigrations::getTableStatus($this->table);

            if ($status['count'] === 0) {
                return;
            }

            $this->info("Rolling back latest migration for '{$this->table}'...");

            $results = SmartMigrations::rollbackTable($this->table, [
                'latest' => true,
                'force' => true,
            ]);

            $migration = $results->first();

            // Notify team
            \Notification::send(
                User::where('role', 'admin')->get(),
                new MigrationRolledBackNotification(
                    $this->table,
                    $migration['migration'],
                    $this->reason
                )
            );

            \Log::warning("Rolled back migration for {$this->table}: {$this->reason}");
        } catch (\Exception $e) {
            \Log::error("Failed to rollback {$this->table}: {$e->getMessage()}");
            throw $e;
        }
    }
}

// Dispatch it:
dispatch(
    new RollbackProblematicMigration('users', 'High error rate detected')
);
```

---

### Example 3: Development Workflow Helper

**Scenario:** Rapid development - quickly reset tables while iterating.

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

class DevReset extends Command
{
    protected $signature = 'dev:reset {--tables=* : Tables to reset}';
    protected $description = 'Quickly reset tables during development';

    public function handle()
    {
        $tables = $this->option('tables');

        if (empty($tables)) {
            $tables = $this->getTablesToReset();
        }

        foreach ($tables as $table) {
            try {
                $this->info("Resetting table: {$table}");

                // Rollback all
                SmartMigrations::rollbackTable($table, [
                    'all' => true,
                    'force' => true,
                ]);

                // Re-migrate
                \Artisan::call('migrate', [
                    '--path' => database_path("migrations"),
                ]);

                $this->line("✓ Reset {$table}");
            } catch (\Exception $e) {
                $this->error("Failed: {$e->getMessage()}");
            }
        }

        $this->info('Reset complete!');
    }

    private function getTablesToReset(): array
    {
        return [
            'users',
            'posts',
            'comments',
        ];
    }
}
```

**Usage:**
```bash
# Reset all default tables
php artisan dev:reset

# Reset specific tables
php artisan dev:reset --tables=users --tables=posts
```

---

### Example 4: Monitoring & Health Check

**Scenario:** Monitor migration status and ensure database is healthy.

```php
<?php

namespace App\Services;

use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

class MigrationHealthMonitor
{
    public function check(): array
    {
        $criticalTables = [
            'users',
            'posts',
            'orders',
        ];

        $status = [
            'healthy' => true,
            'tables' => [],
            'warnings' => [],
        ];

        foreach ($criticalTables as $table) {
            try {
                $tableStatus = SmartMigrations::getTableStatus($table);

                $status['tables'][$table] = [
                    'migrations' => $tableStatus['count'],
                    'batches' => $tableStatus['batches'],
                    'latest_batch' => $tableStatus['latest_batch'],
                ];

                // Check for issues
                if ($tableStatus['count'] === 0) {
                    $status['warnings'][] = "Table '{$table}' has no migrations";
                    $status['healthy'] = false;
                }

                if ($tableStatus['latest_batch'] < 1) {
                    $status['warnings'][] = "Table '{$table}' not properly migrated";
                    $status['healthy'] = false;
                }
            } catch (\Exception $e) {
                $status['warnings'][] = "Error checking table '{$table}': {$e->getMessage()}";
                $status['healthy'] = false;
            }
        }

        return $status;
    }

    public function report(): string
    {
        $check = $this->check();

        $output = $check['healthy'] ? '✓ HEALTHY' : '✗ UNHEALTHY';

        foreach ($check['warnings'] as $warning) {
            $output .= "\n⚠ {$warning}";
        }

        return $output;
    }
}

// Usage:
$monitor = app(MigrationHealthMonitor::class);
echo $monitor->report();

// In health check endpoint:
Route::get('/health/migrations', function () {
    $monitor = app(MigrationHealthMonitor::class);
    $status = $monitor->check();

    return response()->json(
        $status,
        $status['healthy'] ? 200 : 503
    );
});
```

---

### Example 5: Testing with Rollback

**Scenario:** Reset database state between tests.

```php
<?php

namespace Tests\Feature;

use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->artisan('migrate');
    }

    protected function tearDown(): void
    {
        // Clean up specific tables after each test
        try {
            SmartMigrations::rollbackTable('orders', [
                'all' => true,
                'force' => true,
            ]);
        } catch (\Exception $e) {
            // Table might not exist
        }

        parent::tearDown();
    }

    it('creates order successfully', function () {
        $order = Order::factory()->create();

        expect($order)->toBeInstanceOf(Order::class);
        expect($order->id)->not->toBeNull();
    });

    it('updates order', function () {
        $order = Order::factory()->create(['status' => 'pending']);

        $order->update(['status' => 'completed']);

        expect($order->status)->toBe('completed');
    });
}
```

---

### Example 6: CI/CD Integration

**Scenario:** Verify migrations in your CI/CD pipeline.

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: root
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - name: Install dependencies
        run: composer install

      - name: Run migrations
        run: php artisan migrate

      - name: Verify rollback works
        run: php artisan migrate:rollback-table users --all --force

      - name: Re-migrate
        run: php artisan migrate

      - name: Run tests
        run: ./vendor/bin/pest

      - name: Check migrations status
        run: php artisan migrate:list-table-migrations users
```

---

## Common Use Cases

### Use Case 1: Fixing a Broken Migration

```bash
# 1. List migrations for affected table
php artisan migrate:list-table-migrations users

# 2. Rollback the problematic one (usually latest)
php artisan migrate:rollback-table users --latest --force

# 3. Fix the migration file
# Edit: database/migrations/TIMESTAMP_fix_users.php

# 4. Re-run migrations
php artisan migrate

# 5. Verify
php artisan migrate:list-table-migrations users
```

### Use Case 2: Deploying a Feature with Multiple Migrations

```bash
# In development, you created 3 migrations for a feature
# They're all in the same batch

# Before deploying, verify locally
php artisan migrate:rollback-batch 5 --show

# Deploy
git push origin feature/my-feature

# In production, all 3 are deployed in one batch
# Later, if needed, rollback the entire batch
php artisan migrate:rollback-batch 5 --force
```

### Use Case 3: Debugging Production Issue

```bash
# Get timeline of changes
php artisan migrate:list-table-migrations users

# See when the issue started
php artisan migrate:status

# Rollback to known good state
php artisan migrate:rollback-table users --batch=3

# If that fixes it, keep it and deploy fix
# If not, rollback further
php artisan migrate:rollback-table users --batch=2
```

### Use Case 4: Schema Inspection

```php
<?php

// Get all column changes for a table
$migrations = SmartMigrations::listMigrationsForTable('users');

foreach ($migrations as $migration) {
    echo "Batch {$migration['batch']}: {$migration['migration']}\n";
}

// Output:
// Batch 1: 2024_01_01_000001_create_users_table
// Batch 2: 2024_01_15_000001_add_email_verified_to_users_table
// Batch 3: 2024_01_20_000001_add_two_factor_to_users_table
```

---

## Troubleshooting Scenarios

### Scenario 1: "Cannot rollback multiple batches"

```bash
# Error: Cannot rollback migrations from multiple batches without --force

# Solution 1: Use --force if you're sure
php artisan migrate:rollback-table users --all --force

# Solution 2: Rollback one batch at a time
php artisan migrate:rollback-table users --batch=3
php artisan migrate:rollback-table users --batch=2

# Solution 3: Disable check in config
# config/smart-migrations.php
'prevent_multi_batch_rollback' => false,
```

### Scenario 2: "No migrations found"

```bash
# Error: No migrations found for table 'nonexistent'

# Solution 1: Check table name
php artisan migrate:list-table-migrations users  # Instead of 'nonexistent'

# Solution 2: Table doesn't exist - check if migrations were run
php artisan migrate:status

# Solution 3: Verify migrations directory
ls database/migrations/
```

### Scenario 3: "Model not found"

```bash
# Error: Model 'User' not found

# Solution 1: Use full namespace
php artisan migrate:rollback-model App\\Models\\User --latest

# Solution 2: Check model namespace config
php artisan tinker
>>> config('smart-migrations.model_namespace')

# Solution 3: Update config
# config/smart-migrations.php
'model_namespace' => 'App\\Domain\\Models',
```

### Scenario 4: Migration rollback fails

```bash
# Error: SQLSTATE[HY000]: General error: ...

# The migration's down() method has an error

# Solution:
# 1. Check migration file for errors
# 2. Manually revert changes if needed
# 3. Fix the down() method
# 4. Update migrations table if needed

php artisan tinker
>>> DB::table('migrations')->where('migration', 'bad_migration')->delete()
```

---

## Development Setup

### Local Development Environment

```bash
# Clone repo
git clone https://github.com/sirval/laravel-smart-migrations.git
cd laravel-smart-migrations

# Install dependencies
composer install

# Create test environment
cp .env.example .env

# Generate key
php artisan key:generate

# Run tests
./vendor/bin/pest

# Run code style fixes
./vendor/bin/pint

# Run static analysis
./vendor/bin/phpstan analyse src/
```

### Development Commands

```bash
# Run all checks before committing
composer test        # Run tests
composer pint       # Fix code style
composer stan       # PHPStan analysis
composer check-all  # Run all

# View test coverage
./vendor/bin/pest --coverage

# Watch tests (re-run on file change)
./vendor/bin/pest --watch
```

### IDE Setup (VS Code)

```json
// .vscode/settings.json
{
    "php.suggest.basic": false,
    "[php]": {
        "editor.defaultFormatter": "DEJAN.php-cs-fixer",
        "editor.formatOnSave": true
    },
    "php-cs-fixer.executablePath": "${workspaceFolder}/vendor/bin/php-cs-fixer",
    "intelephense.diagnosticsLevel": "strict"
}
```

### IDE Setup (PhpStorm)

1. Settings → PHP → CodeSniffer
2. Set path to `vendor/bin/phpcs`
3. Settings → PHP → Quality Tools → PHPStan
4. Set path to `vendor/bin/phpstan`

---

## Documentation Style Guide

When writing documentation:

1. **Use Clear Headers**
   ```markdown
   # Title (H1)
   ## Section (H2)
   ### Subsection (H3)
   ```

2. **Include Examples**
   ```markdown
   **Example:**
   ```bash
   php artisan command
   ```
   ```

3. **Use Tables for Options**
   ```markdown
   | Option | Type | Default | Description |
   |--------|------|---------|-------------|
   | --force | bool | false | Skip confirmation |
   ```

4. **Code Blocks with Language**
   ````markdown
   ```php
   // PHP code
   ```

   ```bash
   # Bash command
   ```
   ````

5. **Callouts for Important Info**
   ```markdown
   > **Note:** This is important
   > **Warning:** Be careful here
   > **Tip:** This is helpful
   ```

---

## Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Pest PHP](https://pestphp.com/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPStan Documentation](https://phpstan.org/)

---

**Questions?** Open an issue on GitHub or email ohukaiv@gmail.com

Happy contributing! 🚀
