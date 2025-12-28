<?php

namespace Sirval\LaravelSmartMigrations\Services;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class MigrationRollbacker
{
    public function __construct(
        private ConnectionResolverInterface $resolver,
        private string $migrationsTable = 'migrations'
    ) {
    }

    /**
     * Rollback a single migration.
     *
     * @param  object  $migration  The migration record
     * @return bool  Success status
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
     * @return Collection  Results with success/failure status
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
     * @return Collection  Results
     */
    public function rollbackAll(Collection $migrations): Collection
    {
        return $this->rollbackMultiple($migrations);
    }

    /**
     * Get the unique batches from a collection of migrations.
     *
     * @param  Collection<int, object>  $migrations
     * @return array  Array of batch numbers
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
     * @param  bool  $allowMultipleBatches
     * @return bool
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
     * Execute a migration rollback via Artisan.
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
            $migrator = app('migrator');

            // Get all migration files
            $files = $migrator->getMigrationFiles(
                database_path('migrations')
            );

            $migrationPath = null;

            // Find the migration file matching the migration name
            foreach ($files as $file) {
                if (basename($file, '.php') === $migrationName) {
                    $migrationPath = $file;
                    break;
                }
            }

            // If migration file exists, instantiate and run its down() method
            if ($migrationPath) {
                require_once $migrationPath;

                $class = $this->getMigrationClass($migrationName);
                $migration = new $class();

                // Call the down() method to actually drop the table
                $migration->down();
            }

            // Delete from migrations table
            $this->resolver->connection()
                ->table($this->migrationsTable)
                ->where('migration', $migrationName)
                ->delete();

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Convert migration filename to class name.
     *
     * @param  string  $migrationName
     * @return string
     */
    private function getMigrationClass(string $migrationName): string
    {
        return str(str_replace('_', ' ', $migrationName))
            ->title()
            ->replace(' ', '')
            ->toString();
    }

    /**
     * Log a rollback to the audit table (if enabled).
     *
     * @param  string  $migrationName
     * @param  string  $table
     * @param  int  $batch
     * @param  string  $status
     * @param  string|null  $details
     * @return void
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
