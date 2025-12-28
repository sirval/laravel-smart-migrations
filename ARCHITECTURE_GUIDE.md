# Architecture & Developer Guide

Deep dive into the Laravel Smart Migrations package architecture and how to extend it.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Service Layer Design](#service-layer-design)
3. [Command Architecture](#command-architecture)
4. [Facade Pattern](#facade-pattern)
5. [Exception Handling](#exception-handling)
6. [Configuration System](#configuration-system)
7. [Extending the Package](#extending-the-package)
8. [Design Patterns](#design-patterns)
9. [Performance Considerations](#performance-considerations)
10. [Security Considerations](#security-considerations)

---

## Architecture Overview

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    User Interface Layer                         │
├──────────────────────────────────────────────────────────────────┤
│  Commands: RollbackByTableCommand, RollbackByModelCommand, etc. │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│                  Facade Layer (SmartMigrations)                 │
│              Provides programmatic API to services              │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│                    Service Layer (Business Logic)               │
├──────────────────────────────────────────────────────────────────┤
│ ┌──────────────────┐  ┌──────────────────┐  ┌────────────────┐ │
│ │MigrationFinder   │  │MigrationParser   │  │ModelResolver   │ │
│ │ - findByTable    │  │ - parseFromFile  │  │ - resolveTable │ │
│ │ - findByBatch    │  │ - parseFromClass │  │ - validateModel│ │
│ │ - getLatestFor   │  │ - parseFromName  │  └────────────────┘ │
│ └──────────────────┘  └──────────────────┘  ┌────────────────┐ │
│ ┌──────────────────────────────────────────┐ │MigrationRoll   │ │
│ │         SmartMigrations Service          │ │- rollbackOne   │ │
│ │ - rollbackTable/rollbackModel/rollback   │ │- rollbackMany  │ │
│ │ - listMigrations/getStatus               │ └────────────────┘ │
│ └──────────────────────────────────────────┘                    │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│                 Data Access Layer (Laravel)                     │
├──────────────────────────────────────────────────────────────────┤
│ Database (migrations table) │ Schema Builder │ File System      │
└──────────────────────────────────────────────────────────────────┘
```

### Directory Structure

```
src/
├── Commands/
│   ├── RollbackByTableCommand.php
│   ├── RollbackByModelCommand.php
│   ├── RollbackByBatchCommand.php
│   ├── ListTableMigrationsCommand.php
│   └── ListModelMigrationsCommand.php
│
├── Services/
│   ├── SmartMigrations.php              (Main orchestrator)
│   ├── MigrationFinder.php              (Queries migrations)
│   ├── MigrationParser.php              (Extracts metadata)
│   ├── MigrationRollbacker.php          (Executes rollback)
│   └── ModelResolver.php                (Resolves models)
│
├── Exceptions/
│   ├── NoMigrationsFoundException.php
│   └── ModelNotFoundException.php
│
├── Facades/
│   └── SmartMigrations.php              (Facade for service)
│
├── LaravelSmartMigrations.php           (Main container class)
└── LaravelSmartMigrationsServiceProvider.php

config/
└── smart-migrations.php                 (Configuration)

tests/
├── Unit/                                (Service tests)
├── Feature/                             (Command tests)
└── Fixtures/                            (Test migrations)
```

---

## Service Layer Design

### MigrationFinder Service

**Purpose:** Query and retrieve migration records from the database.

**Responsibilities:**
- Query the `migrations` table
- Filter by table, batch, timestamp
- Return results in consistent format

**Key Methods:**

```php
public function findByTable(string $table): Collection
// Finds all migrations that modified a specific table
// Returns: Collection of migration records from database

public function findByBatch(int $batch): Collection
// Finds all migrations in a specific batch
// Returns: Collection ordered by batch descending

public function getLatestForTable(string $table): ?array
// Finds the most recent migration for a table
// Returns: Single migration record or null

public function findByTimestamp(string $timestamp): ?array
// Finds a specific migration by timestamp
// Returns: Single migration record or null
```

**Design Decisions:**
- Uses Laravel's `DB` facade for queries
- Returns `Collection` for consistency
- Ordered by batch descending (newest first)
- Handles case sensitivity of migration names

**Data Format:**

```php
[
    'id' => 1,
    'migration' => '2024_01_01_000001_create_users_table',
    'batch' => 1,
]
```

---

### MigrationParser Service

**Purpose:** Extract metadata from migration files and class names.

**Responsibilities:**
- Parse migration files for `Schema::create/table`
- Extract table names from class names
- Extract table names from migration filenames
- Handle various naming conventions

**Key Methods:**

```php
public function parseTableFromMigrationFile(string $filePath): string
// Reads migration file and extracts table name from Schema calls
// Returns: Table name or throws exception if cannot parse

public function parseTableFromClassName(string $className): string
// Converts class name to table name using heuristics
// E.g., CreateUsersTable -> users
// Returns: Table name

public function parseTableFromMigrationName(string $migrationName): string
// Extracts table from migration filename
// E.g., add_email_to_users_table -> users
// Returns: Table name
```

**Parsing Strategies:**

1. **File Content Parsing** (Most Accurate)
   - Reads the migration file
   - Uses regex to find `Schema::create('table'` or `Schema::table('table'`
   - Handles quoted table names
   - Pros: Works with any table name
   - Cons: Requires file access, slower

2. **Class Name Parsing** (Fast)
   - Converts `CreateUsersTable` to `users`
   - Handles snake_case conversion: `CreateUserProfilesTable` -> `user_profiles`
   - Assumes Laravel naming conventions
   - Pros: Fast, doesn't require file access
   - Cons: Won't work with custom classes

3. **Migration Name Parsing** (Fallback)
   - Extracts from filename: `add_email_to_users_table` -> `users`
   - Handles various prefixes: create, add, alter, rename, etc.
   - Pros: Always available
   - Cons: Heuristic-based, may fail with unusual names

**Design Decision:** Use file content parsing first, fall back to class name, then migration name.

---

### ModelResolver Service

**Purpose:** Resolve Eloquent model classes to database table names.

**Responsibilities:**
- Load and instantiate model classes
- Extract table name from model's `$table` property or convention
- Validate models exist
- Handle custom namespaces

**Key Methods:**

```php
public function resolveTableFromModel(string $model): string
// Converts model name to table name
// Handles: User, App\Models\User, nested\models\Post
// Returns: Table name
// Throws: ModelNotFoundException if model not found

public function validateModelExists(string $model): bool
// Checks if a model class can be loaded
// Returns: boolean

public function resolveFullyQualifiedClass(string $model): string
// Builds full class path from short name
// Uses config model_namespace
// Returns: Fully qualified class name
```

**Namespace Resolution:**

```php
// Config: 'model_namespace' => 'App\Models'

// Short name
SmartMigrations::rollbackModel('User')
// Resolves to: App\Models\User

// Nested short name
SmartMigrations::rollbackModel('Blog\Post')
// Resolves to: App\Models\Blog\Post

// Full path (bypasses config)
SmartMigrations::rollbackModel('App\Other\User')
// Uses: App\Other\User (as-is)
```

**Design Decision:** Support both short and fully qualified names for flexibility.

---

### MigrationRollbacker Service

**Purpose:** Execute migration rollbacks safely.

**Responsibilities:**
- Execute `down()` method of migrations
- Log rollback results
- Handle failures gracefully
- Maintain batch ordering

**Key Methods:**

```php
public function rollbackSingle(array $migration, bool $force = false): array
// Rolls back a single migration
// Returns: Result array with status

public function rollbackMultiple(
    Collection $migrations, 
    bool $force = false
): Collection
// Rolls back multiple migrations in order
// Maintains batch order (newest first)
// Returns: Collection of results

public function validateBeforeRollback(
    Collection $migrations
): bool
// Confirms user wants to proceed
// Returns: boolean
```

**Rollback Flow:**

```
Input: Collection of migrations
   ↓
Order by batch descending (newest first)
   ↓
For each migration:
   - Load migration class
   - Call down() method
   - Catch exceptions
   - Record result
   ↓
Return collection of results
```

**Result Format:**

```php
[
    'migration' => '2024_01_01_000001_create_users_table',
    'rolled_back_at' => '2024-12-20 10:30:00',
    'status' => 'success|failed',
    'batch' => 1,
    'error' => null, // only if status = failed
]
```

**Design Decision:** Always process migrations in batch order to prevent foreign key constraint violations.

---

### SmartMigrations Service (Orchestrator)

**Purpose:** Coordinate all services to provide high-level migration operations.

**Responsibilities:**
- Delegate to appropriate services
- Apply command options (--latest, --oldest, --all, etc.)
- Filter migrations based on options
- Provide convenient public API

**Key Methods:**

```php
public function rollbackTable(string $table, array $options = []): Collection
// High-level API for rolling back table migrations
// Applies options filtering
// Returns: Collection of results

public function rollbackModel(string $model, array $options = []): Collection
// High-level API for rolling back model migrations
// Resolves model to table first
// Returns: Collection of results

public function getTableStatus(string $table): array
// Returns detailed status of table's migrations
// Includes: count, batches, latest_batch, migrations
```

**Options Processing:**

```php
// Supported options:
[
    'latest' => true,      // Only latest migration
    'oldest' => true,      // Only oldest migration
    'all' => true,         // All migrations
    'batch' => 5,          // All from batch 5
    'force' => true,       // Skip confirmation
    'dry_run' => true,     // Preview without executing
]

// If no options → defaults to 'latest'
```

---

## Command Architecture

### Base Command Pattern

All commands extend Laravel's `Command` class and follow a consistent pattern:

```php
class RollbackByTableCommand extends Command
{
    protected $signature = 'migrate:rollback-table {table} {...options}';
    protected $description = 'Rollback all migrations for a specific table';

    public function __construct(
        public MigrationFinder $finder,
        public MigrationRollbacker $rollbacker,
        // ... other dependencies
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            // Validation
            $table = $this->argument('table');

            // Business Logic (use services)
            $migrations = $this->finder->findByTable($table);

            // Output & Interaction
            $this->displayMigrations($migrations);
            $confirmed = $this->confirmRollback();

            // Execution
            if ($confirmed) {
                $results = $this->rollbacker->rollbackMultiple($migrations);
            }

            // Result Reporting
            $this->reportResults($results);

            return self::SUCCESS;
        } catch (NoMigrationsFoundException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
```

### Command Interaction Flow

```
User runs command
         ↓
Parse arguments & options
         ↓
Find relevant migrations (via service)
         ↓
If none found → Show error → Exit
         ↓
If multiple found → Show options menu (if no specific option given)
         ↓
Show migration details
         ↓
Request confirmation (if configured)
         ↓
User confirms
         ↓
Execute rollback (via service)
         ↓
Report results to console
         ↓
Exit with status code
```

### Return Codes

```php
// SUCCESS = 0
$this->call('some:command'); // Command succeeded

// FAILURE = 1
return self::FAILURE; // General failure

// INVALID = 2
return self::INVALID; // Invalid arguments/options
```

---

## Facade Pattern

### Facade Implementation

```php
// src/Facades/SmartMigrations.php
class SmartMigrations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelSmartMigrations::class;
    }
}

// Usage
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

SmartMigrations::rollbackTable('users', ['latest' => true]);
```

### How It Works

1. **Registration:** Service provider binds `LaravelSmartMigrations` to container
2. **Access:** Facade calls `getFacadeAccessor()` to get container binding
3. **Delegation:** All method calls delegated to bound service
4. **Result:** Clean, fluent API without explicit dependency injection

### Advantages

- **Easy to use:** No need to inject dependencies
- **Testable:** Can be mocked in tests
- **Static-like:** Feels like static methods but still testable
- **Magic:** PHP magic methods handle delegation

### Implementation Details

```php
// When you call:
SmartMigrations::rollbackTable('users', ['latest' => true]);

// Behind the scenes:
1. Facade gets accessor: LaravelSmartMigrations::class
2. Resolves from container: app(LaravelSmartMigrations::class)
3. Gets wrapped service: $laravel->getSmartMigrations()
4. Calls method: $service->rollbackTable('users', ['latest' => true])
5. Returns result
```

---

## Exception Handling

### Custom Exceptions

```php
// NoMigrationsFoundException
// Thrown when no migrations match the criteria
throw NoMigrationsFoundException::forTable('users');

// ModelNotFoundException
// Thrown when a model cannot be loaded
throw ModelNotFoundException::notFound('User');
```

### Exception Hierarchy

```
Exception (PHP)
├── NoMigrationsFoundException
│   - forTable(string $table): self
│   - forModel(string $model): self
│   - forBatch(int $batch): self
│   - generic(string $message): self
│
└── ModelNotFoundException
    - notFound(string $model): self
    - classDoesNotExist(string $class): self
    - notAModel(string $class): self
```

### Exception Handling in Commands

```php
public function handle(): int
{
    try {
        // Code that might throw
        $migrations = $this->finder->findByTable($table);
        
        if ($migrations->isEmpty()) {
            throw new NoMigrationsFoundException(
                "No migrations found for table '{$table}'."
            );
        }

        return self::SUCCESS;
    } catch (NoMigrationsFoundException $e) {
        $this->error($e->getMessage());
        return self::FAILURE;
    } catch (ModelNotFoundException $e) {
        $this->error($e->getMessage());
        $this->line('Check your model namespace in config/smart-migrations.php');
        return self::FAILURE;
    } catch (\Exception $e) {
        $this->error("Unexpected error: {$e->getMessage()}");
        return self::FAILURE;
    }
}
```

---

## Configuration System

### Configuration File

```php
// config/smart-migrations.php
return [
    'model_namespace' => 'App\\Models',
    'require_confirmation' => true,
    'show_details' => true,
    'prevent_multi_batch_rollback' => true,
    'audit_log_enabled' => false,
    'audit_log_table' => 'smart_migrations_audits',
];
```

### Accessing Configuration

```php
// In services
config('smart-migrations.model_namespace')

// In commands
config('smart-migrations.require_confirmation')

// With defaults
config('smart-migrations.custom_option', 'default_value')
```

### Environment-Based Configuration

```php
// In config/smart-migrations.php
return [
    'audit_log_enabled' => env('SMART_MIGRATIONS_AUDIT', false),
    'require_confirmation' => env('SMART_MIGRATIONS_CONFIRM', true),
];

// In .env
SMART_MIGRATIONS_AUDIT=true
SMART_MIGRATIONS_CONFIRM=false
```

---

## Extending the Package

### Creating Custom Commands

```php
<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

class RollbackSpecificMigration extends Command
{
    protected $signature = 'migrate:rollback-specific {table} {--count=1}';
    protected $description = 'Rollback N recent migrations for a table';

    public function handle()
    {
        $table = $this->argument('table');
        $count = $this->option('count');

        // Use the package's API
        $migrations = SmartMigrations::listMigrationsForTable($table);

        // Custom logic
        $toRollback = $migrations->take($count);

        foreach ($toRollback as $migration) {
            $this->line("Rolling back: {$migration['migration']}");
            // Execute rollback...
        }
    }
}
```

### Creating Custom Services

```php
<?php

namespace App\Services;

use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

class MigrationScheduler
{
    public function scheduleRollback(string $table, \DateTimeInterface $when)
    {
        // Schedule a rollback for later
        dispatch(
            new RollbackMigration($table),
            $when
        );
    }
}

// Use it:
app(MigrationScheduler::class)->scheduleRollback('users', now()->addHours(2));
```

### Adding Event Listeners

```php
<?php

namespace App\Listeners;

use Sirval\LaravelSmartMigrations\Events\MigrationRolledBack;

class LogMigrationRollback
{
    public function handle(MigrationRolledBack $event)
    {
        \Log::info("Migration rolled back", [
            'table' => $event->table,
            'migration' => $event->migration,
            'user' => auth()->user()?->id,
        ]);
    }
}
```

---

## Design Patterns

### Service Locator Pattern

The package uses dependency injection for services:

```php
// Instead of:
$finder = new MigrationFinder(...);

// We use:
$finder = app(MigrationFinder::class); // or inject in constructor
```

**Benefits:**
- Testable (can mock in tests)
- Configurable (can override in service provider)
- Loose coupling (services don't directly depend on each other)

### Repository Pattern

`MigrationFinder` acts as a repository:

```php
// Instead of directly querying:
DB::table('migrations')->where('...')->get();

// We use the repository:
$finder->findByTable('users');
```

**Benefits:**
- Abstraction from database structure
- Reusable queries
- Easy to test (can mock)
- Single responsibility

### Facade Pattern

`SmartMigrations` facade provides a clean API:

```php
// Instead of:
app(SmartMigrations::class)->rollbackTable(...)

// We use:
SmartMigrations::rollbackTable(...)
```

**Benefits:**
- Clean, discoverable API
- No imports in code
- Laravel convention
- Testable

### Strategy Pattern

Multiple rollback strategies:

```php
// By table
SmartMigrations::rollbackTable($table, ['latest' => true]);

// By model
SmartMigrations::rollbackModel($model, ['latest' => true]);

// By batch
SmartMigrations::rollbackBatch($batch);
```

Each strategy encapsulates different logic for same operation.

---

## Performance Considerations

### Database Queries

**Current Approach:**
- Single query to find migrations
- Single query per rollback execution

**Optimizations:**
- Cache migration metadata
- Batch load migration classes
- Connection pooling

### File System Access

**Migration Parsing:**
- Reads migration files for table name extraction
- Can be slow with many files

**Optimization:**
- Cache parsed table names in memory
- Use filename-based heuristics first
- Only read files when necessary

### Code Examples

```php
// SLOW: Multiple queries
foreach ($tables as $table) {
    $migrations = $finder->findByTable($table); // N queries
}

// BETTER: Single query
$migrations = $finder->findByMultipleTables($tables); // 1 query

// FAST: Use cache
$migrations = Cache::remember(
    "migrations_{$table}",
    3600, // 1 hour
    fn() => $finder->findByTable($table)
);
```

---

## Security Considerations

### 1. Authorization

Commands should be protected by authentication:

```php
// In command
if (!auth()->check()) {
    $this->error('Unauthorized');
    return self::FAILURE;
}

// Or middleware (if using API)
Route::post('/rollback', RollbackAction::class)->middleware('auth');
```

### 2. Audit Logging

Track who performs rollbacks:

```php
$audit = new MigrationAudit([
    'user_id' => auth()->id(),
    'table' => $table,
    'action' => 'rollback',
    'timestamp' => now(),
]);
$audit->save();
```

### 3. Backup Requirements

Always backup before rollback in production:

```php
// Best practice in documentation
// Before running rollback:
// 1. Backup database: mysqldump -u root database > backup.sql
// 2. Test rollback in staging
// 3. Then deploy to production
```

### 4. Configuration Security

Don't expose sensitive config:

```php
// DON'T: Expose config in responses
response()->json(config('smart-migrations'));

// DO: Only expose what's needed
response()->json(['status' => 'ok']);
```

### 5. Input Validation

Validate all user input:

```php
$table = $this->argument('table');

// Validate table exists in database
if (!Schema::hasTable($table)) {
    $this->error("Table does not exist: {$table}");
    return self::FAILURE;
}

// Validate model exists
if (!$this->modelResolver->validateModelExists($model)) {
    throw ModelNotFoundException::notFound($model);
}
```

---

## Contributing to the Package

### Code Standards

- PSR-12 coding standard
- Type hints for all parameters and returns
- PHPStan level 8 analysis
- 90%+ test coverage

### Adding Features

1. Create a new branch: `git checkout -b feature/name`
2. Write tests first (TDD)
3. Implement feature
4. Ensure all tests pass
5. Document changes
6. Submit PR

### Submitting PRs

- Clear description of what changes
- Why it's needed
- Any breaking changes
- Test results
- Documentation updates

---

## Debugging

### Enable Query Logging

```php
// In a test or command
DB::enableQueryLog();

// Run commands
SmartMigrations::rollbackTable('users', ['latest' => true]);

// See queries
dd(DB::getQueryLog());
```

### Dump Service State

```php
$status = SmartMigrations::getTableStatus('users');
dd($status);

// Output:
// [
//     'table' => 'users',
//     'count' => 3,
//     'batches' => [1, 2, 3],
//     'latest_batch' => 3,
//     'migrations' => [...]
// ]
```

### Enable Command Debugging

```bash
# Add verbose flag to commands
php artisan migrate:rollback-table users --latest -vvv
```

---

## Future Enhancements

Potential areas for expansion:

1. **Audit Logging**
   - Track all rollbacks
   - Integration with audit trail packages

2. **Scheduling**
   - Schedule rollbacks for specific times
   - Cron-based automated rollbacks

3. **Event System**
   - Events before/after rollback
   - Integration with webhooks

4. **Graphical Interface**
   - Web-based dashboard
   - Visualize migration history

5. **Advanced Filtering**
   - Rollback by date range
   - Rollback by author
   - Rollback by risk level

6. **Notifications**
   - Slack notifications on rollback
   - Email alerts
   - Team notifications

---

For questions or contributions, see [CONTRIBUTING.md](./CONTRIBUTING.md)
