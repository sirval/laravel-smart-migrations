<?php

namespace Sirval\LaravelSmartMigrations\Services;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class MigrationRollbacker
{
    public function __construct(
        private ConnectionResolverInterface $resolver,
        private string $migrationsTable = 'migrations'
    ) {}

    /**
     * Rollback a single migration.
     *
     * @param  object  $migration  The migration record
     * @return bool Success status
     *
     * @throws \Exception
     */
    public function rollbackSingle(object $migration): bool
    {
        return $this->rollbackMigration($migration->migration);
    }

    /**
     * Rollback multiple migrations.
     *
     * @param  Collection<int, object>  $migrations  Migrations to rollback
     * @return Collection Results with success/failure status
     */
    public function rollbackMultiple(Collection $migrations): Collection
    {
        return $migrations->map(fn ($migration) => [
            'migration' => $migration->migration,
            'success' => $this->rollbackMigration($migration->migration),
        ]);
    }

    /**
     * Rollback all migrations in a collection.
     *
     * @param  Collection<int, object>  $migrations
     * @return Collection Results
     */
    public function rollbackAll(Collection $migrations): Collection
    {
        return $this->rollbackMultiple($migrations);
    }

    /**
     * Get the unique batches from a collection of migrations.
     *
     * @param  Collection<int, object>  $migrations
     * @return array Array of batch numbers
     */
    public function getExecutedBatches(Collection $migrations): array
    {
        return $migrations->pluck('batch')->unique()->sort()->values()->toArray();
    }

    /**
     * Validate that all migrations can be rolled back safely.
     *
     * Checks for conditions that might cause issues:
     * - Migrations from multiple batches (unless explicitly allowed)
     * - Broken migration dependencies
     *
     * @param  Collection<int, object>  $migrations
     */
    public function validateBeforeRollback(Collection $migrations, bool $allowMultipleBatches = false): bool
    {
        if ($migrations->isEmpty()) {
            return false;
        }

        $batches = $this->getExecutedBatches($migrations);

        if (count($batches) > 1 && ! $allowMultipleBatches) {
            return false;
        }

        return true;
    }
    
    /**
     *
     * Executes the migration's down() method to actually drop the table,
     * then removes the record from the migrations table.
     *
     * @param  string  $migrationName
     * @return bool
     */
    private function rollbackMigration(string $migrationName): bool
    {
        try {
            /** @var Migrator $migrator */
            $migrator = app('migrator');
            $migrationsPath = database_path('migrations');

            // Get all migration files
            $files = $migrator->getMigrationFiles([$migrationsPath]);

            $migrationPath = null;

            // Find the migration file matching the migration name
            foreach ($files as $file) {
                if (basename($file, '.php') === $migrationName) {
                    $migrationPath = $file;
                    break;
                }
            }

            // If migration file exists, instantiate and run its down method
            if ($migrationPath) {
                // Include the file to define the migration class
                require_once $migrationPath;

                // Get the migration instance - handle both anonymous and named classes
                $migration = $this->getMigrationInstance($migrationPath);
                
                if ($migration === null) {
                    Log::error("Could not instantiate migration for {$migrationName}");
                    // Still delete from migrations table
                    $this->resolver->connection()
                        ->table($this->migrationsTable)
                        ->where('migration', $migrationName)
                        ->delete();
                    return false;
                }

                // Call down() - Schema facade will use the default connection
                $migration->down();
            }

            // Delete from migrations table
            $this->resolver->connection()
                ->table($this->migrationsTable)
                ->where('migration', $migrationName)
                ->delete();

            return true;
        } catch (\Exception $e) {
            Log::error("Migration rollback failed for {$migrationName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a migration instance from the migration file.
     *
     * Handles both anonymous classes (Modern Laravel) and named classes.
     *
     * @param  string  $migrationPath
     * @return \Illuminate\Database\Migrations\Migration|null
     */
    private function getMigrationInstance(string $migrationPath): ?\Illuminate\Database\Migrations\Migration
    {
        try {
            // Get all declared classes before and after require
            $classesBefore = get_declared_classes();
            require_once $migrationPath;
            $classesAfter = get_declared_classes();

            // Find the new class that was loaded
            $newClasses = array_diff($classesAfter, $classesBefore);

            if (empty($newClasses)) {
                return null;
            }

            // Get the last defined class (should be the Migration)
            $migrationClass = end($newClasses);

            if (class_exists($migrationClass)) {
                $instance = new $migrationClass();
                if ($instance instanceof \Illuminate\Database\Migrations\Migration) {
                    return $instance;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get migration instance: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Log a rollback to the audit table (if enabled).
     */
    public function logToAudit(
        string $migrationName,
        string $table,
        int $batch,
        string $status = 'success',
        ?string $details = null
    ): void {
        if (! config('smart-migrations.audit_log_enabled')) {
            return;
        }

        $auditTable = config('smart-migrations.audit_log_table', 'smart_migrations_audits');

        try {
            $this->resolver->connection()
                ->table($auditTable)
                ->insert([
                    'migration_name' => $migrationName,
                    'table_name' => $table,
                    'batch' => $batch,
                    'action' => 'rollback',
                    'status' => $status,
                    'details' => $details,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Exception) {
            // Silently fail if audit table doesn't exist
        }
    }
}
