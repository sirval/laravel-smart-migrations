# Laravel Smart Migrations - Implementation Plan

## Project Overview
A Laravel package that intelligently manages migrations with focus on **safe rollback by table name or model**, with expansion to analysis, validation, and optimization features.

**Current Status:**
- Package skeleton created with Spatie's `laravel-package-tools`
- Composer dependencies resolved (Pest 3.x for PHP 8.2 compatibility)
- Base service provider and command structure ready
- Config file and tests scaffolding in place

---

## Architecture Overview

```
SmartRollback Package Structure:
├── Commands (5 commands)
│   ├── RollbackByTableCommand       - Main: migrate:rollback-table {table}
│   ├── RollbackByModelCommand       - Alt: migrate:rollback-model {model}
│   ├── RollbackByBatchCommand       - Alt: migrate:rollback-batch {batch}
│   ├── ListTableMigrationsCommand   - List: migrate:list-table-migrations {table}
│   └── ListModelMigrationsCommand   - List: migrate:list-model-migrations {model}
│
├── Services (4 core services)
│   ├── MigrationFinder              - Locate migrations by table/model
│   ├── MigrationParser              - Extract table name from migration class
│   ├── MigrationRollbacker          - Execute rollback safely
│   └── ModelResolver                - Resolve model → table name
│
├── Exceptions (2 custom exceptions)
│   ├── NoMigrationsFoundException   - Thrown when no migrations match
│   └── ModelNotFoundException       - Thrown when model not found
│
├── Facades
│   └── SmartRollback               - Public API facade
│
└── LaravelSmartRollbackServiceProvider
    - Register commands, services, config, etc.
```

---

## Implementation Phases

### Phase 1: Core Service Layer (Foundation)
**Goal:** Build the engine that finds and parses migrations.

#### 1.1 `MigrationFinder` Service
- **Responsibility:** Query migrations table and filter by table/batch/timestamp
- **Methods:**
  - `findByTable(string $table): Collection` — Find all migrations for a table
  - `findByModel(string $model): Collection` — Find migrations for model's table
  - `findByBatch(int $batch): Collection` — Find migrations in a specific batch
  - `findByTimestamp(string $timestamp): Migration|null` — Find single migration by ID
  - `getMigrationRecords(): Collection` — Get all migration records from DB
  - `getLatestForTable(string $table): Migration|null` — Get most recent migration for table

**Data Source:** Laravel's `migrations` table (uses `migrate:refresh`, etc.)

#### 1.2 `MigrationParser` Service
- **Responsibility:** Extract metadata from migration files and class names
- **Methods:**
  - `parseTableFromMigrationFile(string $filePath): string` — Parse migration file for table (regex on Schema::create/table)
  - `parseTableFromClassName(string $className): string` — Heuristic parsing (e.g., "CreateUsersTable" → "users")
  - `parseTableFromMigrationName(string $migrationName): string` — Extract from filename timestamp + name (e.g., "2024_05_05_000001_change_users_email_nullable" → "users")
  - `extractModelFromNamespace(string $namespace): string|null` — Extract model name from full namespace

**Challenges:**
- Not all migrations follow conventions (custom table names in Schema::create)
- Need fallback heuristics
- Case sensitivity (CreateUsersTable vs create_users_table)

#### 1.3 `ModelResolver` Service
- **Responsibility:** Convert model class to database table name
- **Methods:**
  - `resolveTableFromModel(string $modelClass): string` — Get table from model (uses model's `$table` property or convention)
  - `validateModelExists(string $modelClass): bool` — Check if model class can be loaded
  - `getModelNamespace(string $modelName): string` — Build full namespace (assumes `App\Models\`)

**Notes:**
- Leverage Laravel's schema builder `(new Model())->getTable()`
- Handle custom namespaces via config

#### 1.4 `MigrationRollbacker` Service
- **Responsibility:** Execute rollbacks safely with validation
- **Methods:**
  - `rollbackSingle(Migration $migration): void` — Rollback one migration
  - `rollbackMultiple(Collection $migrations): Collection` — Rollback multiple (returns results)
  - `rollbackAll(Collection $migrations): Collection` — Rollback all for table
  - `validateBeforeRollback(Collection $migrations): bool` — Confirm user intent
  - `getExecutedBatches(Collection $migrations): array` — Group by batch number

**Safety Features:**
- Require explicit confirmation for rollbacks > 1 migration
- Show which batches will be affected
- Prevent rolling back migrations from multiple batches unless `--force` used
- Log rollbacks to audit trail

---

### Phase 2: Commands (User Interface)
**Goal:** Create commands that use the services to provide safe, interactive rollback options.

#### 2.1 `RollbackByTableCommand` (Primary)
**Signature:** `php artisan migrate:rollback-table {table} {--options}`

**Flow:**
1. User specifies table name
2. Find all migrations for table
3. If none found → throw `NoMigrationsFoundException`
4. If 1 migration → show confirmation and rollback
5. If multiple → show options menu:
   - `--latest` — Rollback most recent
   - `--oldest` — Rollback oldest
   - `--all` — Rollback all (with warning)
   - `--interactive` — Show numbered list, user selects
   - `--file=TIMESTAMP` — Rollback specific migration by ID
   - `--batch=N` — Rollback all from batch N
   - `--force` — Skip safety checks

**Output Example:**
```
Error: Multiple migrations found for table 'users'. Please specify an option:

Migrations found (5):
  [1] 2024_05_05_000001_change_users_email_nullable.php ✓ (batch 5)
  [2] 2024_04_10_000001_add_profile_photo_to_users_table.php ✓ (batch 4)
  [3] 2024_03_20_000001_add_two_factor_to_users_table.php ✓ (batch 3)
  [4] 2024_02_15_000001_add_email_verified_to_users_table.php ✓ (batch 2)
  [5] 2024_01_01_000001_create_users_table.php ✓ (batch 1)

Available options:
  --latest          Rollback migration [1] (most recent)
  --oldest          Rollback migration [5] (oldest)
  --all             Rollback all 5 migrations (use with caution!)
  --interactive     Choose from interactive menu
  --file=2024_05_05_000001  Rollback specific migration by timestamp
  --batch=3         Rollback all migrations from batch 3
```

#### 2.2 `RollbackByModelCommand` (Alternative)
**Signature:** `php artisan migrate:rollback-model {model} {--options}`

**Flow:**
1. User specifies model name (e.g., "User", "App\Models\User")
2. Use `ModelResolver` to get table name
3. Proceed as `RollbackByTableCommand`

**Benefit:** User-friendly if they think in terms of models, not table names.

#### 2.3 `RollbackByBatchCommand` (Utility)
**Signature:** `php artisan migrate:rollback-batch {batch} {--force}`

**Flow:**
1. Show all migrations in batch
2. Confirm and rollback all

#### 2.4 `ListTableMigrationsCommand` (Info)
**Signature:** `php artisan migrate:list-table-migrations {table}`

**Output:** Pretty-printed list of all migrations for table (no rollback).

#### 2.5 `ListModelMigrationsCommand` (Info)
**Signature:** `php artisan migrate:list-model-migrations {model}`

**Output:** Pretty-printed list of all migrations for model's table (no rollback).

---

### Phase 3: Facades & Public API
**Goal:** Allow programmatic use of the package.

#### 3.1 `SmartMigrations` Facade
**Methods:**
```php
SmartMigrations::rollbackTable(string $table, array $options = []): Collection
SmartMigrations::rollbackModel(string $model, array $options = []): Collection
SmartMigrations::rollbackBatch(int $batch, bool $force = false): Collection
SmartMigrations::listMigrationsForTable(string $table): Collection
SmartMigrations::listMigrationsForModel(string $model): Collection
```

**Example Usage:**
```php
$results = SmartMigrations::rollbackTable('users', ['latest' => true]);
```

---

### Phase 4: Configuration
**Goal:** Allow users to customize behavior.

#### 4.1 `config/smart-migrations.php`
```php
return [
    // Model namespace (when user passes "User" instead of full path)
    'model_namespace' => 'App\\Models',
    
    // Require explicit confirmation before rollback
    'require_confirmation' => true,
    
    // Show migration details before rollback
    'show_details' => true,
    
    // Batch safety: prevent rolling back migrations from multiple batches
    'prevent_multi_batch_rollback' => true,
    
    // Enable audit logging
    'audit_log_enabled' => false,
    'audit_log_table' => 'smart_migrations_audits',
];
```

---

### Phase 5: Exceptions & Error Handling
**Goal:** Provide clear, actionable errors.

#### 5.1 Custom Exceptions
```php
// NoMigrationsFoundException
- Throw when no migrations found for table/model
- Suggest available tables
- Include helpful CLI command to list migrations

// ModelNotFoundException
- Throw when model class cannot be loaded
- Suggest checking model namespace config
- List available models
```

---

### Phase 6: Testing Strategy
**Goal:** Ensure reliability and prevent regressions.

#### 6.1 Unit Tests (`tests/Unit/`)
```
MigrationFinderTest
  ✓ finds migrations by table name
  ✓ handles multiple migrations per table
  ✓ returns empty collection for unknown table
  ✓ finds migrations by batch
  ✓ finds migration by timestamp

MigrationParserTest
  ✓ extracts table name from migration file (Schema::create)
  ✓ extracts table name from migration file (Schema::table)
  ✓ parses class name heuristics
  ✓ handles custom table names
  ✓ extracts model from namespace

ModelResolverTest
  ✓ resolves model to table name
  ✓ validates model exists
  ✓ handles custom model namespace
  ✓ throws ModelNotFoundException for invalid model
```

#### 6.2 Feature Tests (`tests/Feature/`)
```
RollbackByTableCommandTest
  ✓ displays error when multiple migrations found
  ✓ shows available options
  ✓ executes --latest option
  ✓ executes --oldest option
  ✓ executes --all option
  ✓ executes --file option
  ✓ executes --batch option
  ✓ requires confirmation before rollback
  ✓ logs rollback to audit trail

RollbackByModelCommandTest
  ✓ resolves model to table
  ✓ works like RollbackByTableCommand
  ✓ throws ModelNotFoundException

RollbackByBatchCommandTest
  ✓ rolls back all migrations in batch
  ✓ shows batch details
  ✓ requires confirmation

ListMigrationsCommandTest
  ✓ displays all migrations for table
  ✓ shows batch numbers
  ✓ works for models
```

#### 6.3 Test Database Setup
- Use `orchestra/testbench` (already in require-dev)
- Create fixture migrations in `tests/Fixtures/migrations/`
- Each test creates temp migrations and rolls them back

---

## Implementation Order (Recommended)

1. **Exceptions** — Define custom exceptions (quick, no dependencies)
2. **MigrationFinder** — Query migrations table (foundation for all services)
3. **MigrationParser** — Parse migrations (foundation for commands)
4. **ModelResolver** — Resolve models (used by ModelCommand)
5. **MigrationRollbacker** — Execute rollbacks (used by all commands)
6. **RollbackByTableCommand** — Primary command (uses all services)
7. **RollbackByModelCommand** — Secondary command (uses ModelResolver)
8. **RollbackByBatchCommand** — Batch command (uses MigrationRollbacker)
9. **ListTableMigrationsCommand** — Info command
10. **ListModelMigrationsCommand** — Info command
11. **SmartRollback Facade** — Programmatic API
12. **Config File** — Configuration options
13. **Service Provider Updates** — Register all commands/services
14. **Tests** — Unit tests for services, feature tests for commands
15. **Documentation** — README, CONTRIBUTING, examples

---

## Key Design Decisions

### 1. Why Multiple Commands?
- **RollbackByTable:** Most intuitive for developers (they know table names)
- **RollbackByModel:** Convenient for model-centric developers
- **RollbackByBatch:** Useful for rolling back entire migrations batches
- **ListCommands:** Inspect without risking rollback

### 2. Safety-First Approach
- Require explicit options for multiple migrations (no single "rollback all")
- Show affected batches and migration details
- Optional audit logging for compliance
- Confirmation prompts configurable

### 3. Heuristic Parsing
- Migration parser uses multiple strategies:
  1. Parse migration file for `Schema::create/table`
  2. Extract from migration filename (e.g., `add_column_to_users_table`)
  3. Fallback to class name parsing (e.g., `CreateUsersTable`)
  
  This handles custom conventions gracefully.

### 4. Service-Command Separation
- **Services** — Pure business logic, testable, reusable
- **Commands** — CLI presentation layer, uses services
- **Facade** — Programmatic API, wraps services

This allows using the package in jobs, event listeners, etc.

---

## File Checklist

### Core Services (`src/Services/`)
- [ ] `MigrationFinder.php`
- [ ] `MigrationParser.php`
- [ ] `MigrationRollbacker.php`
- [ ] `ModelResolver.php`

### Commands (`src/Commands/`)
- [ ] Update `LaravelSmartRollbackCommand.php` → `RollbackByTableCommand.php`
- [ ] `RollbackByModelCommand.php`
- [ ] `RollbackByBatchCommand.php`
- [ ] `ListTableMigrationsCommand.php`
- [ ] `ListModelMigrationsCommand.php`

### Exceptions (`src/Exceptions/`)
- [ ] `NoMigrationsFoundException.php`
- [ ] `ModelNotFoundException.php`

### Facades (`src/Facades/`)
- [ ] Update `SmartRollback.php` facade

### Core Package Files
- [ ] Update `LaravelSmartMigrationsServiceProvider.php`
- [ ] Update `config/smart-migrations.php`

### Tests
- [ ] `tests/Unit/MigrationFinderTest.php`
- [ ] `tests/Unit/MigrationParserTest.php`
- [ ] `tests/Unit/ModelResolverTest.php`
- [ ] `tests/Feature/RollbackByTableCommandTest.php`
- [ ] `tests/Feature/RollbackByModelCommandTest.php`
- [ ] `tests/Feature/RollbackByBatchCommandTest.php`
- [ ] `tests/Fixtures/migrations/` — Test migration files

### Documentation
- [ ] Update `README.md`
- [ ] Create `CONTRIBUTING.md`
- [ ] Update `CHANGELOG.md`

### CI/CD (already present, may need updates)
- [ ] `.github/workflows/run-tests.yml`
- [ ] `.github/workflows/fix-php-code-style-issues.yml`
- [ ] `.github/workflows/phpstan.yml`

---

## Expected Behavior Summary

### Happy Path Example
```bash
# User has 5 migrations for 'users' table
$ php artisan migrate:rollback-table users

Error: Multiple migrations found for table 'users'. Please specify an option:

Migrations found (5):
  [1] 2024_05_05_000001_change_users_email_nullable.php ✓ (batch 5)
  [2] 2024_04_10_000001_add_profile_photo_to_users_table.php ✓ (batch 4)
  [3] 2024_03_20_000001_add_two_factor_to_users_table.php ✓ (batch 3)
  [4] 2024_02_15_000001_add_email_verified_to_users_table.php ✓ (batch 2)
  [5] 2024_01_01_000001_create_users_table.php ✓ (batch 1)

Available options:
  --latest          Rollback migration [1] (most recent)
  --oldest          Rollback migration [5] (oldest)
  --all             Rollback all 5 migrations (use with caution!)
  --interactive     Choose from interactive menu
  --file=2024_05_05_000001  Rollback specific migration by timestamp
  --batch=3         Rollback all migrations from batch 3

# User chooses
$ php artisan migrate:rollback-table users --latest

Rolling back: 2024_05_05_000001_change_users_email_nullable.php
✓ Rolled back successfully

# Alternative: by model
$ php artisan migrate:rollback-model User --latest

Rolling back: 2024_05_05_000001_change_users_email_nullable.php
✓ Rolled back successfully

# List migrations without rolling back
$ php artisan migrate:list-table-migrations users

Migrations for table 'users':
  [1] 2024_05_05_000001_change_users_email_nullable.php (batch 5)
  [2] 2024_04_10_000001_add_profile_photo_to_users_table.php (batch 4)
  [3] 2024_03_20_000001_add_two_factor_to_users_table.php (batch 3)
  [4] 2024_02_15_000001_add_email_verified_to_users_table.php (batch 2)
  [5] 2024_01_01_000001_create_users_table.php (batch 1)
```

---

## Next Steps

1. **Review & Approve Plan** — Confirm design aligns with your vision
2. **Phase 1 Implementation** — Build services (MigrationFinder → MigrationRollbacker)
3. **Phase 2 Implementation** — Build commands (RollbackByTableCommand → ListModelMigrationsCommand)
4. **Phase 3–5** — Add facade, config, exceptions
5. **Phase 6** — Comprehensive test suite
6. **Documentation** — Polish README and examples
7. **Beta Testing** — Use in real projects, gather feedback
8. **Release** — Publish to Packagist

---

## Questions for Clarification

1. **Audit Logging:** Should rollbacks be logged to a database table for compliance? (default: disabled in config)
2. **Batch Safety:** Prevent rolling back migrations from different batches? (default: true)
3. **Custom Namespaces:** Support custom model namespaces beyond `App\Models`? (default: via config)
4. **Migration Naming:** Assume all migrations follow Laravel conventions, or support custom patterns?
5. **Rollback Direction:** Always rollback in batch order (newest first), or allow arbitrary order?

---

## Summary
This plan provides a **robust, safe, and user-friendly** package for rolling back migrations by table or model name. The architecture prioritizes:
- **Safety:** Multiple confirmations, batch awareness, audit trails
- **Usability:** Clear error messages, interactive options, helpful suggestions
- **Extensibility:** Service layer allows programmatic use, config allows customization
- **Quality:** Comprehensive tests, type-safe code, clear separation of concerns

Ready to proceed with implementation when you give the go-ahead!
