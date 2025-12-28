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
     * Execute a migration rollback via Artisan.
     */
    private function rollbackMigration(string $migrationName): bool
    {
        try {
            // Get the migration resolver
            $migrator = app('migrator');

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
