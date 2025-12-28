# Phase 6: Comprehensive Testing Guide

Complete testing strategy for Laravel Smart Migrations package.

## Table of Contents

1. [Testing Overview](#testing-overview)
2. [Unit Tests](#unit-tests)
3. [Feature Tests](#feature-tests)
4. [Running Tests](#running-tests)
5. [Test Database Setup](#test-database-setup)
6. [Writing Custom Tests](#writing-custom-tests)
7. [Coverage Goals](#coverage-goals)

---

## Testing Overview

The package uses **Pest PHP** for testing, which provides an elegant and modern testing experience.

### Test Structure

```
tests/
├── Unit/
│   ├── MigrationFinderTest.php
│   ├── MigrationParserTest.php
│   ├── ModelResolverTest.php
│   └── MigrationRollbackerTest.php
├── Feature/
│   ├── CommandFeatureTest.php
│   ├── RollbackByTableCommandTest.php
│   ├── RollbackByModelCommandTest.php
│   ├── RollbackByBatchCommandTest.php
│   ├── ListMigrationsCommandTest.php
│   └── SmartMigrationsServiceTest.php
├── Fixtures/
│   └── migrations/
│       ├── 2024_01_01_000001_create_test_users_table.php
│       ├── 2024_01_01_000002_add_email_to_test_users_table.php
│       └── ... more fixture migrations
├── Pest.php (Configuration)
└── TestCase.php (Base test class)
```

---

## Unit Tests

Unit tests verify the core services in isolation.

### MigrationFinderTest

Tests the `MigrationFinder` service.

```php
<?php

namespace Tests\Unit;

use Illuminate\Database\DatabaseManager;
use Sirval\LaravelSmartMigrations\Services\MigrationFinder;
use Tests\TestCase;

class MigrationFinderTest extends TestCase
{
    private MigrationFinder $finder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finder = app(MigrationFinder::class);
    }

    it('finds migrations by table name', function () {
        // Setup: Run migrations that target 'users' table
        $this->runMigration('2024_01_01_000001_create_users_table.php');
        $this->runMigration('2024_01_01_000002_add_email_to_users_table.php');

        // Test
        $migrations = $this->finder->findByTable('users');

        expect($migrations)->toHaveCount(2);
        expect($migrations->first()['migration'])->toContain('create_users_table');
        expect($migrations->last()['migration'])->toContain('add_email_to_users_table');
    });

    it('returns empty collection for unknown table', function () {
        $migrations = $this->finder->findByTable('nonexistent_table');

        expect($migrations)->toBeEmpty();
    });

    it('finds migrations by batch number', function () {
        // Setup: Run migrations in different batches
        // Batch 1
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        // Batch 2 (simulated by running separately)
        $this->runMigration('2024_01_01_000002_add_email_to_users_table.php');

        // Test
        $batchOneMigrations = $this->finder->findByBatch(1);
        expect($batchOneMigrations)->toHaveCount(1);

        $batchTwoMigrations = $this->finder->findByBatch(2);
        expect($batchTwoMigrations)->toHaveCount(1);
    });

    it('gets latest migration for table', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');
        $this->runMigration('2024_01_02_000001_add_email_to_users_table.php');

        $latest = $this->finder->getLatestForTable('users');

        expect($latest)->not->toBeNull();
        expect($latest['migration'])->toContain('add_email_to_users_table');
    });

    it('handles multiple migrations per table', function () {
        // Run 5 migrations for users table
        for ($i = 1; $i <= 5; $i++) {
            $this->runMigration("2024_01_0{$i}_000001_migration_{$i}.php");
        }

        $migrations = $this->finder->findByTable('users');

        expect($migrations->count())->toEqual(5);
    });

    it('returns migrations ordered by batch desc', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');
        $this->runMigration('2024_01_02_000001_add_email_to_users_table.php');
        $this->runMigration('2024_01_03_000001_add_phone_to_users_table.php');

        $migrations = $this->finder->findByTable('users');

        // Should be ordered newest batch first
        $batches = $migrations->pluck('batch')->toArray();
        expect($batches)->toEqual(array_reverse(sort($batches) ?: $batches));
    });
}
```

### MigrationParserTest

Tests the `MigrationParser` service.

```php
<?php

namespace Tests\Unit;

use Sirval\LaravelSmartMigrations\Services\MigrationParser;
use Tests\TestCase;

class MigrationParserTest extends TestCase
{
    private MigrationParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(MigrationParser::class);
    }

    it('extracts table name from migration filename', function () {
        // Convention: 2024_01_01_000001_create_users_table.php
        $table = $this->parser->parseTableFromMigrationName(
            '2024_01_01_000001_create_users_table'
        );

        expect($table)->toEqual('users');
    });

    it('extracts table name from add column migration', function () {
        $table = $this->parser->parseTableFromMigrationName(
            '2024_01_01_000001_add_email_to_users_table'
        );

        expect($table)->toEqual('users');
    });

    it('extracts table name from class name', function () {
        $table = $this->parser->parseTableFromClassName('CreateUsersTable');

        expect($table)->toEqual('users');
    });

    it('handles underscore separated class names', function () {
        $table = $this->parser->parseTableFromClassName('CreateUserProfilesTable');

        expect($table)->toEqual('user_profiles');
    });

    it('parses table from migration file content', function () {
        $migrationContent = <<<'PHP'
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
        PHP;

        $table = $this->parser->parseTableFromMigrationFile($migrationContent);

        expect($table)->toEqual('users');
    });

    it('parses table from schema table syntax', function () {
        $migrationContent = <<<'PHP'
        Schema::table('users', function (Blueprint $table) {
            $table->string('email');
        });
        PHP;

        $table = $this->parser->parseTableFromMigrationFile($migrationContent);

        expect($table)->toEqual('users');
    });

    it('handles custom table names in migrations', function () {
        $migrationContent = <<<'PHP'
        Schema::create('custom_table_name', function (Blueprint $table) {
            $table->id();
        });
        PHP;

        $table = $this->parser->parseTableFromMigrationFile($migrationContent);

        expect($table)->toEqual('custom_table_name');
    });

    it('handles quoted table names', function () {
        $migrationContent = <<<'PHP'
        Schema::create("users", function (Blueprint $table) {
            $table->id();
        });
        PHP;

        $table = $this->parser->parseTableFromMigrationFile($migrationContent);

        expect($table)->toEqual('users');
    });
}
```

### ModelResolverTest

Tests the `ModelResolver` service.

```php
<?php

namespace Tests\Unit;

use Sirval\LaravelSmartMigrations\Exceptions\ModelNotFoundException;
use Sirval\LaravelSmartMigrations\Services\ModelResolver;
use Tests\TestCase;

class ModelResolverTest extends TestCase
{
    private ModelResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ModelResolver::class);
    }

    it('resolves model to table name', function () {
        // Using a real Laravel model
        $table = $this->resolver->resolveTableFromModel('User');

        expect($table)->toEqual('users');
    });

    it('resolves fully qualified model class', function () {
        $table = $this->resolver->resolveTableFromModel('App\\Models\\User');

        expect($table)->toEqual('users');
    });

    it('resolves model with custom table name', function () {
        // Assuming a model with: protected $table = 'system_users'
        $table = $this->resolver->resolveTableFromModel('SystemUser');

        expect($table)->toEqual('system_users');
    });

    it('validates model exists', function () {
        $exists = $this->resolver->validateModelExists('User');

        expect($exists)->toBeTrue();
    });

    it('returns false for nonexistent model', function () {
        $exists = $this->resolver->validateModelExists('NonexistentModel');

        expect($exists)->toBeFalse();
    });

    it('throws ModelNotFoundException for invalid model', function () {
        expect(fn() => $this->resolver->resolveTableFromModel('InvalidModel'))
            ->toThrow(ModelNotFoundException::class);
    });

    it('handles nested model namespaces', function () {
        $table = $this->resolver->resolveTableFromModel('Blog\\Post');

        // Assumes model_namespace config is 'App\Models'
        // Looks for: App\Models\Blog\Post
        expect($table)->toEqual('posts');
    });

    it('handles custom model namespace', function () {
        config()->set('smart-migrations.model_namespace', 'App\\Domain\\Models');

        $table = $this->resolver->resolveTableFromModel('User');

        // Now looks for: App\Domain\Models\User
        expect($table)->toEqual('users');
    });
}
```

### MigrationRollbackerTest

Tests the `MigrationRollbacker` service.

```php
<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Sirval\LaravelSmartMigrations\Services\MigrationRollbacker;
use Tests\TestCase;

class MigrationRollbackerTest extends TestCase
{
    private MigrationRollbacker $rollbacker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rollbacker = app(MigrationRollbacker::class);
    }

    it('rolls back a single migration', function () {
        // Setup: Create and run a migration
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        // Get the migration record
        $migration = DB::table('migrations')
            ->where('migration', 'create_users_table')
            ->first();

        // Execute rollback
        $results = $this->rollbacker->rollbackSingle($migration);

        // Verify table was dropped
        expect(Schema::hasTable('users'))->toBeFalse();
    });

    it('rolls back multiple migrations', function () {
        // Setup
        $this->runMigration('2024_01_01_000001_create_users_table.php');
        $this->runMigration('2024_01_01_000002_add_email_to_users_table.php');

        // Get migrations
        $migrations = DB::table('migrations')
            ->where('migration', 'like', '%users%')
            ->get();

        // Execute rollback
        $results = $this->rollbacker->rollbackMultiple($migrations);

        expect($results)->toHaveCount(2);
        expect(Schema::hasTable('users'))->toBeFalse();
    });

    it('returns collection of results', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $migrations = DB::table('migrations')->get();

        $results = $this->rollbacker->rollbackMultiple($migrations);

        expect($results)->toBeInstanceOf(Collection::class);
        expect($results->first())->toHaveKeys(['migration', 'rolled_back_at', 'status']);
    });

    it('handles rollback failures gracefully', function () {
        // This would require a migration with a faulty down() method
        // Create a test migration that intentionally fails

        $this->runMigration('2024_01_01_000001_create_users_table.php');

        // Manually create a broken migration in DB
        DB::table('migrations')->insert([
            'migration' => 'broken_migration',
            'batch' => 999,
        ]);

        $migrations = DB::table('migrations')
            ->where('migration', 'broken_migration')
            ->get();

        // Should not throw, but mark as failed
        $results = $this->rollbacker->rollbackMultiple($migrations);

        expect($results->first()['status'])->toEqual('failed');
    });

    it('maintains batch order when rolling back', function () {
        // Setup migrations in different batches
        $this->runMigration('2024_01_01_000001_create_users_table.php');   // batch 1
        $this->runMigration('2024_01_02_000001_add_email_to_users_table.php'); // batch 2

        $migrations = DB::table('migrations')
            ->where('migration', 'like', '%users%')
            ->orderBy('batch', 'desc') // Newest first
            ->get();

        $results = $this->rollbacker->rollbackMultiple($migrations);

        // Latest batch should be rolled back first
        expect($results->first()['batch'])->toEqual(2);
        expect($results->last()['batch'])->toEqual(1);
    });
}
```

---

## Feature Tests

Feature tests verify the complete command workflows and user-facing functionality.

### CommandFeatureTest

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CommandFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setup test migrations
        $this->setupTestMigrations();
    }

    // ===== ROLLBACK BY TABLE TESTS =====

    it('rolls back latest migration by table', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');
        $this->runMigration('2024_01_02_000001_add_email_to_users_table.php');

        $this->artisan('migrate:rollback-table', [
            'table' => 'users',
            '--latest' => true,
            '--force' => true,
        ])->assertExitCode(0);

        $migrations = DB::table('migrations')
            ->where('migration', 'like', '%users%')
            ->count();

        expect($migrations)->toEqual(1);
    });

    it('shows error when multiple migrations and no option specified', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');
        $this->runMigration('2024_01_02_000001_add_email_to_users_table.php');

        $this->artisan('migrate:rollback-table', ['table' => 'users'])
            ->expectsOutput('Multiple migrations found for table \'users\'');
    });

    it('rolls back oldest migration by table', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');
        $this->runMigration('2024_01_02_000001_add_email_to_users_table.php');

        $this->artisan('migrate:rollback-table', [
            'table' => 'users',
            '--oldest' => true,
            '--force' => true,
        ])->assertExitCode(0);

        // Oldest (create) should be last one removed when rolling back one by one
        $remaining = DB::table('migrations')
            ->where('migration', 'like', '%users%')
            ->get();

        expect($remaining->count())->toEqual(1);
    });

    it('rolls back all migrations by table', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');
        $this->runMigration('2024_01_02_000001_add_email_to_users_table.php');
        $this->runMigration('2024_01_03_000001_add_phone_to_users_table.php');

        $this->artisan('migrate:rollback-table', [
            'table' => 'users',
            '--all' => true,
            '--force' => true,
        ])->assertExitCode(0);

        expect(Schema::hasTable('users'))->toBeFalse();
    });

    it('requires confirmation for rollback', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $this->artisan('migrate:rollback-table', [
            'table' => 'users',
            '--latest' => true,
        ])->expectsConfirmation(
            'This action is destructive and cannot be undone. Proceed?',
            'yes'
        )->assertExitCode(0);
    });

    // ===== ROLLBACK BY MODEL TESTS =====

    it('rolls back migrations by model name', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $this->artisan('migrate:rollback-model', [
            'model' => 'User',
            '--latest' => true,
            '--force' => true,
        ])->assertExitCode(0);

        expect(Schema::hasTable('users'))->toBeFalse();
    });

    it('throws error for nonexistent model', function () {
        $this->artisan('migrate:rollback-model', [
            'model' => 'NonexistentModel',
            '--latest' => true,
        ])->expectsOutput('Model \'NonexistentModel\' not found');
    });

    it('resolves model with custom table name', function () {
        // Assuming a model with custom table name
        $this->runMigration('2024_01_01_000001_create_system_users_table.php');

        $this->artisan('migrate:rollback-model', [
            'model' => 'SystemUser',
            '--latest' => true,
            '--force' => true,
        ])->assertExitCode(0);

        expect(Schema::hasTable('system_users'))->toBeFalse();
    });

    // ===== ROLLBACK BY BATCH TESTS =====

    it('rolls back by batch number', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');   // batch 1
        $this->runMigration('2024_01_02_000001_create_posts_table.php');  // batch 2

        $this->artisan('migrate:rollback-batch', [
            'batch' => 2,
            '--force' => true,
        ])->assertExitCode(0);

        expect(Schema::hasTable('users'))->toBeTrue();
        expect(Schema::hasTable('posts'))->toBeFalse();
    });

    it('shows batch migrations with --show option', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');   // batch 1

        $this->artisan('migrate:rollback-batch', [
            'batch' => 1,
            '--show' => true,
        ])->expectsOutput('Migrations in batch 1:')
            ->assertExitCode(0);

        // Verify nothing was rolled back
        expect(Schema::hasTable('users'))->toBeTrue();
    });

    // ===== LIST MIGRATIONS TESTS =====

    it('lists migrations for a table', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');
        $this->runMigration('2024_01_02_000001_add_email_to_users_table.php');

        $this->artisan('migrate:list-table-migrations', [
            'table' => 'users',
        ])->expectsOutput('Migrations for table \'users\'')
            ->expectsOutput('create_users_table')
            ->expectsOutput('add_email_to_users_table')
            ->assertExitCode(0);
    });

    it('lists migrations for a model', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $this->artisan('migrate:list-model-migrations', [
            'model' => 'User',
        ])->expectsOutput('Migrations for model \'User\'')
            ->assertExitCode(0);
    });

    it('shows error when no migrations found for table', function () {
        $this->artisan('migrate:list-table-migrations', [
            'table' => 'nonexistent',
        ])->expectsOutput('No migrations found for table \'nonexistent\'')
            ->assertExitCode(1);
    });

    // ===== HELPER METHODS =====

    private function setupTestMigrations(): void
    {
        // Create fixture migrations if needed
        // Copy test migrations to database/migrations
    }

    private function runMigration(string $migrationName): void
    {
        Artisan::call('migrate:install');
        Artisan::call('migrate', [
            '--path' => 'tests/Fixtures/migrations',
        ]);
    }
}
```

### SmartMigrationsServiceTest

```php
<?php

namespace Tests\Feature;

use Sirval\LaravelSmartMigrations\Exceptions\ModelNotFoundException;
use Sirval\LaravelSmartMigrations\Exceptions\NoMigrationsFoundException;
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;
use Tests\TestCase;

class SmartMigrationsServiceTest extends TestCase
{
    it('rolls back table via facade', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $results = SmartMigrations::rollbackTable('users', ['latest' => true]);

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($results)->not->toBeEmpty();
    });

    it('rolls back model via facade', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $results = SmartMigrations::rollbackModel('User', ['latest' => true]);

        expect($results)->not->toBeEmpty();
    });

    it('lists migrations for table', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $migrations = SmartMigrations::listMigrationsForTable('users');

        expect($migrations)->not->toBeEmpty();
        expect($migrations->first())->toHaveKeys(['migration', 'batch']);
    });

    it('throws exception for nonexistent table', function () {
        expect(fn() => SmartMigrations::listMigrationsForTable('nonexistent'))
            ->toThrow(NoMigrationsFoundException::class);
    });

    it('throws exception for nonexistent model', function () {
        expect(fn() => SmartMigrations::rollbackModel('NonexistentModel'))
            ->toThrow(ModelNotFoundException::class);
    });

    it('returns table status', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $status = SmartMigrations::getTableStatus('users');

        expect($status)->toHaveKeys(['table', 'count', 'batches', 'latest_batch']);
        expect($status['table'])->toEqual('users');
        expect($status['count'])->toBeGreaterThan(0);
    });

    it('returns model status', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $status = SmartMigrations::getModelStatus('User');

        expect($status['table'])->toEqual('users');
    });

    it('handles dry run mode', function () {
        $this->runMigration('2024_01_01_000001_create_users_table.php');

        $results = SmartMigrations::rollbackTable('users', [
            'latest' => true,
            'dry_run' => true,
        ]);

        expect($results)->not->toBeEmpty();
        // Verify nothing was actually rolled back
        expect(Schema::hasTable('users'))->toBeTrue();
    });
}
```

---

## Running Tests

### Run All Tests

```bash
./vendor/bin/pest
```

### Run Specific Test File

```bash
./vendor/bin/pest tests/Feature/CommandFeatureTest.php
```

### Run Tests with Coverage

```bash
./vendor/bin/pest --coverage

# With detailed report
./vendor/bin/pest --coverage --coverage-html coverage
```

### Run Tests in Parallel

```bash
./vendor/bin/pest --parallel
```

### Run Specific Test

```bash
./vendor/bin/pest tests/Unit/MigrationFinderTest.php --filter "finds migrations by table"
```

### Watch Mode (Re-run on file change)

```bash
./vendor/bin/pest --watch
```

---

## Test Database Setup

### TestCase Base Class

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase, WithWorkbench;

    protected function getPackageProviders($app)
    {
        return [
            'Sirval\LaravelSmartMigrations\LaravelSmartMigrationsServiceProvider',
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        // Run your application migrations here
        $this->artisan('migrate', [
            '--database' => 'testing',
        ])->run();
    }
}
```

### Setting Up Test Migrations

Create fixture migrations in `tests/Fixtures/migrations/`:

```php
<?php
// tests/Fixtures/migrations/2024_01_01_000001_create_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
```

---

## Writing Custom Tests

### Test Template

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class YourNewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup before each test
    }

    it('does something', function () {
        // Arrange
        $this->runMigration('your_migration.php');

        // Act
        $result = $this->artisan('your:command');

        // Assert
        $result->assertExitCode(0);
    });
}
```

### Testing with Database Assertions

```php
it('creates migration record', function () {
    // Before
    $count = DB::table('migrations')->count();

    // Action
    Artisan::call('migrate');

    // After
    expect(DB::table('migrations')->count())->toBeGreaterThan($count);
});
```

### Testing Command Output

```php
it('displays correct output', function () {
    $this->artisan('migrate:list-table-migrations', ['table' => 'users'])
        ->expectsOutput('Migrations for table')
        ->expectsOutput('users')
        ->assertExitCode(0);
});
```

### Testing Exceptions

```php
it('throws exception for invalid input', function () {
    expect(fn() => SmartMigrations::rollbackTable('nonexistent'))
        ->toThrow(NoMigrationsFoundException::class);
});
```

---

## Coverage Goals

Target test coverage for production quality:

| Component | Target | Current |
|-----------|--------|---------|
| Services | 95%+ | - |
| Commands | 90%+ | - |
| Facades | 100% | - |
| Exceptions | 100% | - |
| Overall | 90%+ | - |

### Running Coverage Report

```bash
# Generate coverage report
./vendor/bin/pest --coverage

# Open HTML coverage report
open coverage/index.html
```

### Coverage Standards

- Critical paths: 100%
- Happy paths: 95%+
- Error handling: 90%+
- Edge cases: 80%+

---

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: [8.2, 8.3]
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: sqlite, pdo_sqlite
      
      - name: Install dependencies
        run: composer install --no-interaction
      
      - name: Run tests
        run: ./vendor/bin/pest --coverage
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
```

---

## Best Practices

1. **One assertion per test** (or logically grouped)
2. **Descriptive test names** - Use `it('does something specific')`
3. **Arrange-Act-Assert** pattern
4. **Use fixtures** for common setup
5. **Test edge cases** - Empty results, null values, exceptions
6. **Mock external dependencies** - Database, file system
7. **Isolate tests** - Each test should be independent

---

For more information, see:
- [Pest PHP Documentation](https://pestphp.com/)
- [Orchestra Testbench](https://packages.tools/testbench/)
- [Laravel Testing Docs](https://laravel.com/docs/testing)
