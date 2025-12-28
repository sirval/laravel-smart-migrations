<?php

use Sirval\LaravelSmartMigrations\Services\MigrationFinder;

// Note: Database tests are skipped in unit test suite due to SQLite driver requirement
// These are designed as integration/feature tests to run with proper test environment setup

describe('MigrationFinder', function () {
    it('can be instantiated', function () {
        // Just verify the service can be instantiated via the container
        $finder = app(MigrationFinder::class);

        expect($finder)->toBeInstanceOf(MigrationFinder::class);
    });

    it('has required methods', function () {
        $finder = app(MigrationFinder::class);

        expect($finder)->toHaveMethod('findByTable');
        expect($finder)->toHaveMethod('findByBatch');
        expect($finder)->toHaveMethod('findByTimestamp');
        expect($finder)->toHaveMethod('getMigrationRecords');
        expect($finder)->toHaveMethod('exists');
        expect($finder)->toHaveMethod('getMaxBatch');
    });
})->skip('Database tests skipped - requires SQLite driver setup');
