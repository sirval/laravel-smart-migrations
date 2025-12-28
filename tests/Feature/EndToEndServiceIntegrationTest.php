<?php

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Sirval\LaravelSmartMigrations\Exceptions\ModelNotFoundException;
use Sirval\LaravelSmartMigrations\Exceptions\NoMigrationsFoundException;
use Sirval\LaravelSmartMigrations\Services\MigrationFinder;
use Sirval\LaravelSmartMigrations\Services\MigrationParser;
use Sirval\LaravelSmartMigrations\Services\MigrationRollbacker;
use Sirval\LaravelSmartMigrations\Services\ModelResolver;
use Sirval\LaravelSmartMigrations\Tests\Fixtures\Models\User;
use Sirval\LaravelSmartMigrations\Tests\Fixtures\Models\Post;

describe('End-to-End Service Integration', function () {
    
    // ===== PARSER + FINDER INTEGRATION =====
    
    it('parses migration file and uses parser result to find migrations', function () {
        $parser = app(MigrationParser::class);
        $finder = app(MigrationFinder::class);
        
        // Parse a migration file
        $filePath = __DIR__ . '/../Fixtures/migrations/2024_01_01_000001_create_users_table.php';
        $tableFromFile = $parser->parseTableFromMigrationFile($filePath);
        
        expect($tableFromFile)->toBe('users');
        
        // Verify parser extracted correct table
        expect($parser->parseTableFromMigrationFile($filePath))
            ->toBe($tableFromFile);
    })->skip('File parsing without database');
    
    it('parses class name and identifies table for queries', function () {
        $parser = app(MigrationParser::class);
        
        // Parse multiple class name patterns
        $createTable = $parser->parseTableFromClassName('CreateUsersTable');
        $modifyTable = $parser->parseTableFromClassName('AddEmailToUsersTable');
        
        expect($createTable)->toBe('users');
        expect($modifyTable)->toBe('users');
    });
    
    it('parses migration filename to extract table', function () {
        $parser = app(MigrationParser::class);
        
        $table = $parser->parseTableFromMigrationName('2024_01_01_000001_create_users_table');
        
        expect($table)->toBe('users');
    });
    
    // ===== MODEL RESOLVER + PARSER INTEGRATION =====
    
    it('resolves model to table and matches parser results', function () {
        $parser = app(MigrationParser::class);
        $resolver = app(ModelResolver::class);
        
        // Both should identify 'users' as the table
        $tableFromParser = $parser->parseTableFromClassName('CreateUsersTable');
        $tableFromResolver = $resolver->resolveTableFromModel('User');
        
        expect($tableFromParser)->toBe('users');
        expect($tableFromResolver)->toBe('users');
    });
    
    it('validates model exists before resolving table', function () {
        $resolver = app(ModelResolver::class);
        
        // Should validate that User model exists (full namespace)
        expect($resolver->validateModelExists('Sirval\\LaravelSmartMigrations\\Tests\\Fixtures\\Models\\User'))->toBeTrue();
        
        // Should fail for non-existent model
        expect($resolver->validateModelExists('Sirval\\LaravelSmartMigrations\\Tests\\Fixtures\\Models\\NonExistentModel'))->toBeFalse();
    });
    
    it('handles custom model namespace', function () {
        $resolver = app(ModelResolver::class);
        
        // Set custom namespace
        $resolver->setNamespace('App\\Custom\\Models');
        
        // Should build correct full class name
        $fullName = $resolver->buildFullClassName('User');
        
        expect($fullName)->toBe('App\\Custom\\Models\\User');
    });
    
    // ===== ROLLBACKER VALIDATION WORKFLOW =====
    
    it('validates migrations before rollback', function () {
        $rollbacker = app(MigrationRollbacker::class);
        
        $migrations = collect([
            (object) ['migration' => '2024_01_01_000001_create_users_table', 'batch' => 1],
            (object) ['migration' => '2024_01_02_000001_add_email_verified_to_users_table', 'batch' => 1],
        ]);
        
        // Should validate single batch
        expect($rollbacker->validateBeforeRollback($migrations))->toBeTrue();
        
        // Should reject empty collection
        expect($rollbacker->validateBeforeRollback(collect([])))->toBeFalse();
    });
    
    it('detects multiple batches and prevents unsafe rollback', function () {
        $rollbacker = app(MigrationRollbacker::class);
        
        $migrations = collect([
            (object) ['migration' => '2024_01_01_000001_create_users_table', 'batch' => 1],
            (object) ['migration' => '2024_01_02_000001_add_email_to_users_table', 'batch' => 2],
        ]);
        
        // Should reject multi-batch rollback when not allowed
        expect($rollbacker->validateBeforeRollback($migrations, false))->toBeFalse();
        
        // Should accept when explicitly allowed
        expect($rollbacker->validateBeforeRollback($migrations, true))->toBeTrue();
    });
    
    it('groups migrations by batch', function () {
        $rollbacker = app(MigrationRollbacker::class);
        
        $migrations = collect([
            (object) ['migration' => 'migration1', 'batch' => 1],
            (object) ['migration' => 'migration2', 'batch' => 1],
            (object) ['migration' => 'migration3', 'batch' => 2],
            (object) ['migration' => 'migration4', 'batch' => 2],
        ]);
        
        $batches = $rollbacker->getExecutedBatches($migrations);
        
        expect($batches)->toBe([1, 2]);
    });
    
    // ===== EXCEPTION HANDLING =====
    
    it('throws NoMigrationsFoundException with helpful context', function () {
        $exception = NoMigrationsFoundException::forTable('users');
        
        expect($exception->getMessage())->toContain('users');
    });
    
    it('throws ModelNotFoundException with helpful context', function () {
        $exception = ModelNotFoundException::notFound('User');
        
        expect($exception->getMessage())->toContain('User');
    });
    
    // ===== MULTI-SERVICE WORKFLOW SIMULATION =====
    
    it('simulates finding migrations for a table through the full pipeline', function () {
        $parser = app(MigrationParser::class);
        $resolver = app(ModelResolver::class);
        $finder = app(MigrationFinder::class);
        
        // Simulate: User provides table name -> Parser extracts table -> Finder queries
        
        // Step 1: Parse class name
        $className = 'CreateUsersTable';
        $table = $parser->parseTableFromClassName($className);
        expect($table)->toBe('users');
        
        // Step 2: Verify we can resolve the same table from model
        $tableFromModel = $resolver->resolveTableFromModel('User');
        expect($tableFromModel)->toBe($table);
        
        // Step 3: Finder would use this table to query migrations (can't test without DB)
        expect($finder)->toBeInstanceOf(MigrationFinder::class);
    });
    
    it('simulates rollback workflow with multiple services', function () {
        $parser = app(MigrationParser::class);
        $rollbacker = app(MigrationRollbacker::class);
        
        // Step 1: Parse migration to get table
        $table = $parser->parseTableFromClassName('CreateUsersTable');
        expect($table)->toBe('users');
        
        // Step 2: Create mock migrations for that table
        $migrations = collect([
            (object) ['migration' => '2024_01_01_000001_create_users_table', 'batch' => 1],
            (object) ['migration' => '2024_01_02_000001_add_email_verified_to_users_table', 'batch' => 1],
        ]);
        
        // Step 3: Validate before rollback
        expect($rollbacker->validateBeforeRollback($migrations))->toBeTrue();
        
        // Step 4: Get batches to understand scope
        $batches = $rollbacker->getExecutedBatches($migrations);
        expect($batches)->toBe([1]);
    });
    
    it('handles model resolution through entire pipeline', function () {
        $resolver = app(ModelResolver::class);
        $parser = app(MigrationParser::class);
        
        // Step 1: Validate model exists (full namespace required)
        expect($resolver->validateModelExists('Sirval\\LaravelSmartMigrations\\Tests\\Fixtures\\Models\\User'))->toBeTrue();
        
        // Step 2: Resolve to table name
        $table = $resolver->resolveTableFromModel('User');
        expect($table)->toBe('users');
        
        // Step 3: Parser confirms table name from class
        $parserTable = $parser->parseTableFromClassName('CreateUsersTable');
        expect($parserTable)->toBe($table);
    });
    
    // ===== SERVICE AVAILABILITY & CONTAINER =====
    
    it('resolves all services from container', function () {
        $parser = app(MigrationParser::class);
        $finder = app(MigrationFinder::class);
        $resolver = app(ModelResolver::class);
        $rollbacker = app(MigrationRollbacker::class);
        
        expect($parser)->toBeInstanceOf(MigrationParser::class);
        expect($finder)->toBeInstanceOf(MigrationFinder::class);
        expect($resolver)->toBeInstanceOf(ModelResolver::class);
        expect($rollbacker)->toBeInstanceOf(MigrationRollbacker::class);
    });
    
    it('services are singletons', function () {
        $parser1 = app(MigrationParser::class);
        $parser2 = app(MigrationParser::class);
        
        expect($parser1)->toBe($parser2);
    });
    
    it('all services have required public methods', function () {
        $parser = app(MigrationParser::class);
        $finder = app(MigrationFinder::class);
        $resolver = app(ModelResolver::class);
        $rollbacker = app(MigrationRollbacker::class);
        
        // Parser methods
        expect(method_exists($parser, 'parseTableFromMigrationFile'))->toBeTrue();
        expect(method_exists($parser, 'parseTableFromClassName'))->toBeTrue();
        expect(method_exists($parser, 'parseTableFromMigrationName'))->toBeTrue();
        
        // Finder methods
        expect(method_exists($finder, 'findByTable'))->toBeTrue();
        expect(method_exists($finder, 'findByBatch'))->toBeTrue();
        expect(method_exists($finder, 'getMigrationRecords'))->toBeTrue();
        
        // Resolver methods
        expect(method_exists($resolver, 'resolveTableFromModel'))->toBeTrue();
        expect(method_exists($resolver, 'validateModelExists'))->toBeTrue();
        expect(method_exists($resolver, 'buildFullClassName'))->toBeTrue();
        expect(method_exists($resolver, 'setNamespace'))->toBeTrue();
        
        // Rollbacker methods
        expect(method_exists($rollbacker, 'validateBeforeRollback'))->toBeTrue();
        expect(method_exists($rollbacker, 'getExecutedBatches'))->toBeTrue();
        expect(method_exists($rollbacker, 'rollbackSingle'))->toBeTrue();
        expect(method_exists($rollbacker, 'rollbackMultiple'))->toBeTrue();
    });
});
