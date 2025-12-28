<?php

namespace Sirval\LaravelSmartMigrations\Services;

use Illuminate\Support\Collection;
use Sirval\LaravelSmartMigrations\Exceptions\ModelNotFoundException;
use Sirval\LaravelSmartMigrations\Exceptions\NoMigrationsFoundException;

/**
 * SmartMigrations Service
 *
 * Provides a programmatic API for safely rolling back migrations
 * and inspecting migration information.
 *
 * @example
 * // Rollback latest migration for users table
 * $results = SmartMigrations::rollbackTable('users', ['latest' => true]);
 *
 * // Rollback by model
 * $results = SmartMigrations::rollbackModel('User', ['latest' => true]);
 *
 * // List migrations for table
 * $migrations = SmartMigrations::listMigrationsForTable('users');
 */
class SmartMigrations
{
    public function __construct(
        private MigrationFinder $finder,
        private MigrationParser $parser,
        private MigrationRollbacker $rollbacker,
        private ModelResolver $modelResolver,
    ) {}

    /**
     * Rollback migrations for a specific table.
     *
     * @param  string  $table  The database table name
     * @param  array  $options  Rollback options
     *                          - latest (bool): Rollback only the latest migration
     *                          - oldest (bool): Rollback only the oldest migration
     *                          - all (bool): Rollback all migrations for this table
     *                          - batch (int): Rollback all migrations from specific batch
     *                          - force (bool): Skip confirmation prompts
     *                          - dry_run (bool): Show what would be rolled back without executing
     * @return Collection Results of rollback operations
     *
     * @throws NoMigrationsFoundException
     */
    public function rollbackTable(string $table, array $options = []): Collection
    {
        $migrations = $this->finder->findByTable($table);

        if ($migrations->isEmpty()) {
            throw new NoMigrationsFoundException(
                "No migrations found for table '{$table}'."
            );
        }

        return $this->executeRollback($migrations, $options);
    }

    /**
     * Rollback migrations for a specific model.
     *
     * @param  string  $model  The model class name or fully qualified class name
     * @param  array  $options  Rollback options (see rollbackTable)
     * @return Collection Results of rollback operations
     *
     * @throws ModelNotFoundException
     * @throws NoMigrationsFoundException
     */
    public function rollbackModel(string $model, array $options = []): Collection
    {
        if (! $this->modelResolver->validateModelExists($model)) {
            throw new ModelNotFoundException(
                "Model '{$model}' not found."
            );
        }

        $table = $this->modelResolver->resolveTableFromModel($model);

        return $this->rollbackTable($table, $options);
    }

    /**
     * Rollback all migrations from a specific batch.
     *
     * @param  int  $batch  The batch number
     * @param  array  $options  Rollback options
     *                          - force (bool): Skip confirmation prompts
     *                          - dry_run (bool): Show what would be rolled back without executing
     * @return Collection Results of rollback operations
     *
     * @throws NoMigrationsFoundException
     */
    public function rollbackBatch(int $batch, array $options = []): Collection
    {
        $migrations = $this->finder->findByBatch($batch);

        if ($migrations->isEmpty()) {
            throw new NoMigrationsFoundException(
                "No migrations found for batch #{$batch}."
            );
        }

        return $this->executeRollback($migrations, $options);
    }

    /**
     * List all migrations for a specific table.
     *
     * @param  string  $table  The database table name
     * @return Collection Migration records
     *
     * @throws NoMigrationsFoundException
     */
    public function listMigrationsForTable(string $table): Collection
    {
        $migrations = $this->finder->findByTable($table);

        if ($migrations->isEmpty()) {
            throw new NoMigrationsFoundException(
                "No migrations found for table '{$table}'."
            );
        }

        return $migrations;
    }

    /**
     * List all migrations for a specific model.
     *
     * @param  string  $model  The model class name or fully qualified class name
     * @return Collection Migration records
     *
     * @throws ModelNotFoundException
     * @throws NoMigrationsFoundException
     */
    public function listMigrationsForModel(string $model): Collection
    {
        if (! $this->modelResolver->validateModelExists($model)) {
            throw new ModelNotFoundException(
                "Model '{$model}' not found."
            );
        }

        $table = $this->modelResolver->resolveTableFromModel($model);

        return $this->listMigrationsForTable($table);
    }

    /**
     * Get migration status for a specific table.
     *
     * @param  string  $table  The database table name
     * @return array Status information
     */
    public function getTableStatus(string $table): array
    {
        $migrations = $this->finder->findByTable($table);

        return [
            'table' => $table,
            'count' => $migrations->count(),
            'batches' => $migrations->pluck('batch')->unique()->sort()->toArray(),
            'latest_batch' => $migrations->pluck('batch')->max(),
            'migrations' => $migrations->toArray(),
        ];
    }

    /**
     * Get migration status for a specific model.
     *
     * @param  string  $model  The model class name
     * @return array Status information
     *
     * @throws ModelNotFoundException
     */
    public function getModelStatus(string $model): array
    {
        if (! $this->modelResolver->validateModelExists($model)) {
            throw new ModelNotFoundException(
                "Model '{$model}' not found."
            );
        }

        $table = $this->modelResolver->resolveTableFromModel($model);

        return $this->getTableStatus($table);
    }

    /**
     * Execute the rollback with the specified options.
     *
     *
     * @return Collection Results
     */
    private function executeRollback(Collection $migrations, array $options): Collection
    {
        // Handle dry_run option
        if ($options['dry_run'] ?? false) {
            return $migrations->map(function ($migration) {
                return array_merge($migration, ['status' => 'dry_run']);
            });
        }

        // Handle force option
        $force = $options['force'] ?? false;

        // Filter migrations based on options
        $filtered = $this->filterMigrations($migrations, $options);

        // Execute rollback
        return $this->rollbacker->rollbackMultiple($filtered, $force);
    }

    /**
     * Filter migrations based on provided options.
     */
    private function filterMigrations(Collection $migrations, array $options): Collection
    {
        // Default: rollback latest
        if (empty($options) || ! isset($options['latest']) && ! isset($options['oldest']) && ! isset($options['all'])) {
            return $migrations->sortByDesc('batch')->take(1);
        }

        if ($options['latest'] ?? false) {
            return $migrations->sortByDesc('batch')->take(1);
        }

        if ($options['oldest'] ?? false) {
            return $migrations->sortBy('batch')->take(1);
        }

        if ($options['all'] ?? false) {
            return $migrations;
        }

        if (isset($options['batch'])) {
            return $migrations->where('batch', $options['batch']);
        }

        return $migrations;
    }
}
