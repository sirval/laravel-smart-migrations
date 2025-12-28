<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Sirval\LaravelSmartMigrations\Services\MigrationRollbacker;

// Note: Database tests are skipped in unit test suite due to SQLite driver requirement
// These are designed as integration/feature tests to run with proper test environment setup

describe('MigrationRollbacker', function () {
    it('can be instantiated', function () {
        $rollbacker = app(MigrationRollbacker::class);
        
        expect($rollbacker)->toBeInstanceOf(MigrationRollbacker::class);
    });

    it('can call required methods', function () {
        $rollbacker = app(MigrationRollbacker::class);
        
        // Verify methods exist and are callable
        expect(method_exists($rollbacker, 'rollbackSingle'))->toBeTrue();
        expect(method_exists($rollbacker, 'rollbackMultiple'))->toBeTrue();
        expect(method_exists($rollbacker, 'rollbackAll'))->toBeTrue();
        expect(method_exists($rollbacker, 'getExecutedBatches'))->toBeTrue();
        expect(method_exists($rollbacker, 'validateBeforeRollback'))->toBeTrue();
        expect(method_exists($rollbacker, 'logToAudit'))->toBeTrue();
    });

    it('gets executed batches from migrations', function () {
        $rollbacker = app(MigrationRollbacker::class);
        $migrations = collect([
            (object) ['migration' => '2024_01_01_000001_create_users_table', 'batch' => 1],
            (object) ['migration' => '2024_01_02_000001_add_email_verified_to_users_table', 'batch' => 2],
            (object) ['migration' => '2024_01_03_000001_create_posts_table', 'batch' => 2],
        ]);

        $batches = $rollbacker->getExecutedBatches($migrations);

        expect($batches)->toBe([1, 2]);
    });

    it('validates before rollback - rejects empty migrations', function () {
        $rollbacker = app(MigrationRollbacker::class);
        $migrations = collect([]);

        expect($rollbacker->validateBeforeRollback($migrations))->toBeFalse();
    });

    it('validates before rollback - accepts single batch', function () {
        $rollbacker = app(MigrationRollbacker::class);
        $migrations = collect([
            (object) ['migration' => '2024_01_01_000001_create_users_table', 'batch' => 1],
        ]);

        expect($rollbacker->validateBeforeRollback($migrations))->toBeTrue();
    });

    it('detects multiple batches when not allowed', function () {
        $rollbacker = app(MigrationRollbacker::class);
        $migrations = collect([
            (object) ['migration' => '2024_01_01_000001_create_users_table', 'batch' => 1],
            (object) ['migration' => '2024_01_02_000001_add_email_verified_to_users_table', 'batch' => 2],
        ]);

        expect($rollbacker->validateBeforeRollback($migrations, false))->toBeFalse();
    });

    it('allows multiple batches when explicitly enabled', function () {
        $rollbacker = app(MigrationRollbacker::class);
        $migrations = collect([
            (object) ['migration' => '2024_01_01_000001_create_users_table', 'batch' => 1],
            (object) ['migration' => '2024_01_02_000001_add_email_verified_to_users_table', 'batch' => 2],
        ]);

        expect($rollbacker->validateBeforeRollback($migrations, true))->toBeTrue();
    });

    it('returns unique batches', function () {
        $rollbacker = app(MigrationRollbacker::class);
        $migrations = collect([
            (object) ['migration' => 'migration1', 'batch' => 2],
            (object) ['migration' => 'migration2', 'batch' => 2],
            (object) ['migration' => 'migration3', 'batch' => 2],
        ]);

        $batches = $rollbacker->getExecutedBatches($migrations);

        expect($batches)->toBe([2]);
    });
});
