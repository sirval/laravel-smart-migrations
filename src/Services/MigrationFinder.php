<?php

namespace Sirval\LaravelSmartMigrations\Services;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Collection;

class MigrationFinder
{
    public function __construct(
        private ConnectionResolverInterface $resolver,
        private string $table = 'migrations'
    ) {
    }

    /**
     * Find all migrations for a specific table.
     *
     * @param  string  $tableName  The database table name
     * @return Collection<int, object>
     */
    public function findByTable(string $tableName): Collection
    {
        return $this->getMigrationRecords()
            ->filter(fn ($migration) => $this->extractTableFromMigration($migration->migration) === $tableName);
    }

    /**
     * Find all migrations for a specific batch.
     *
     * @param  int  $batch  The batch number
     * @return Collection<int, object>
     */
    public function findByBatch(int $batch): Collection
    {
        return $this->getMigrationRecords()
            ->filter(fn ($migration) => $migration->batch === $batch);
    }

    /**
     * Find a specific migration by its timestamp.
     *
     * @param  string  $timestamp  The migration timestamp
     * @return object|null
     */
    public function findByTimestamp(string $timestamp): ?object
    {
        return $this->getMigrationRecords()
            ->first(fn ($migration) => str_starts_with($migration->migration, $timestamp));
    }

    /**
     * Get the latest migration for a specific table.
     *
     * @param  string  $tableName  The database table name
     * @return object|null
     */
    public function getLatestForTable(string $tableName): ?object
    {
        return $this->findByTable($tableName)->last();
    }

    /**
     * Get the oldest migration for a specific table.
     *
     * @param  string  $tableName  The database table name
     * @return object|null
     */
    public function getOldestForTable(string $tableName): ?object
    {
        return $this->findByTable($tableName)->first();
    }

    /**
     * Get all migration records from the database.
     *
     * @return Collection<int, object>
     */
    public function getMigrationRecords(): Collection
    {
        return collect(
            $this->resolver->connection()
                ->table($this->table)
                ->orderBy('batch')
                ->orderBy('migration')
                ->get()
                ->all()
        );
    }

    /**
     * Check if a migration exists in the database.
     *
     * @param  string  $migrationName  The migration name
     * @return bool
     */
    public function exists(string $migrationName): bool
    {
        return $this->resolver->connection()
            ->table($this->table)
            ->where('migration', $migrationName)
            ->exists();
    }

    /**
     * Get the total number of batches executed.
     *
     * @return int
     */
    public function getMaxBatch(): int
    {
        return $this->resolver->connection()
            ->table($this->table)
            ->max('batch') ?? 0;
    }

    /**
     * Extract table name from migration filename.
     *
     * Uses heuristic matching to extract table names from migration filenames.
     * Examples:
     *   - 2024_01_01_000001_create_users_table → users
     *   - 2024_01_01_000001_add_email_to_users_table → users
     *   - 2024_01_01_000001_create_posts → posts
     *
     * @param  string  $migration  The migration filename
     * @return string|null
     */
    private function extractTableFromMigration(string $migration): ?string
    {
        // Remove timestamp prefix (e.g., "2024_01_01_000001_")
        $withoutTimestamp = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migration);

        // Remove common suffixes
        $cleaned = preg_replace('/_table$/', '', $withoutTimestamp);

        // Extract table name from patterns like "add_column_to_users"
        if (preg_match('/to_(\w+)$/', $cleaned, $matches)) {
            return $matches[1];
        }

        // Extract from "create_users" or similar
        if (preg_match('/^(create_)?(\w+)/', $cleaned, $matches)) {
            return $matches[2] ?? $matches[1];
        }

        return null;
    }
}
