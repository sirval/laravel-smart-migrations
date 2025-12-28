<?php

namespace Sirval\LaravelSmartMigrations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ForeignKeyDetector
{
    /**
     * Get all foreign key constraints for a table.
     */
    public function detectForeignKeys(string $tableName): Collection
    {
        return collect($this->getForeignKeyConstraints($tableName));
    }

    /**
     * Get foreign key constraints from the database.
     */
    private function getForeignKeyConstraints(string $tableName): array
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => $this->getMysqlForeignKeys($tableName),
            'pgsql' => $this->getPostgresForeignKeys($tableName),
            'sqlite' => $this->getSqliteForeignKeys($tableName),
            default => [],
        };
    }

    /**
     * Get MySQL foreign key constraints.
     */
    private function getMysqlForeignKeys(string $tableName): array
    {
        $database = DB::connection()->getDatabaseName();

        $results = DB::select('
            SELECT
                CONSTRAINT_NAME as constraint_name,
                COLUMN_NAME as column_name,
                REFERENCED_TABLE_NAME as referenced_table,
                REFERENCED_COLUMN_NAME as referenced_column
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$database, $tableName]);

        return array_map(fn ($row) => (array) $row, $results);
    }

    /**
     * Get PostgreSQL foreign key constraints.
     */
    private function getPostgresForeignKeys(string $tableName): array
    {
        $results = DB::select('
            SELECT
                constraint_name,
                column_name,
                referenced_table_name as referenced_table,
                referenced_column_name as referenced_column
            FROM information_schema.key_column_usage
            WHERE table_name = ? AND referenced_table_name IS NOT NULL
        ', [$tableName]);

        return array_map(fn ($row) => (array) $row, $results);
    }

    /**
     * Get SQLite foreign key constraints.
     */
    private function getSqliteForeignKeys(string $tableName): array
    {
        $results = DB::select("PRAGMA foreign_key_list({$tableName})");

        if (empty($results)) {
            return [];
        }

        return array_map(function ($row) {
            $row = (array) $row;

            return [
                'constraint_name' => 'fk_'.$row['table'].'_'.$row['from'],
                'column_name' => $row['from'],
                'referenced_table' => $row['table'],
                'referenced_column' => $row['to'],
            ];
        }, $results);
    }

    /**
     * Check if a table has dependent foreign keys (other tables reference it).
     */
    public function detectDependentKeys(string $tableName): Collection
    {
        return collect($this->getDependentKeyConstraints($tableName));
    }

    /**
     * Get tables that have foreign keys referencing this table.
     */
    private function getDependentKeyConstraints(string $tableName): array
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => $this->getMysqlDependentKeys($tableName),
            'pgsql' => $this->getPostgresDependentKeys($tableName),
            'sqlite' => $this->getSqliteDependentKeys($tableName),
            default => [],
        };
    }

    /**
     * Get MySQL tables that reference this table.
     */
    private function getMysqlDependentKeys(string $tableName): array
    {
        $database = DB::connection()->getDatabaseName();

        $results = DB::select('
            SELECT
                TABLE_NAME as dependent_table,
                COLUMN_NAME as column_name,
                CONSTRAINT_NAME as constraint_name
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?
        ', [$database, $tableName]);

        return array_map(fn ($row) => (array) $row, $results);
    }

    /**
     * Get PostgreSQL tables that reference this table.
     */
    private function getPostgresDependentKeys(string $tableName): array
    {
        $results = DB::select('
            SELECT
                table_name as dependent_table,
                column_name,
                constraint_name
            FROM information_schema.key_column_usage
            WHERE referenced_table_name = ?
        ', [$tableName]);

        return array_map(fn ($row) => (array) $row, $results);
    }

    /**
     * Get SQLite tables that reference this table.
     */
    private function getSqliteDependentKeys(string $tableName): array
    {
        try {
            $allTables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $dependentKeys = [];

            foreach ($allTables as $table) {
                $tableName2 = $table->name;
                $foreignKeys = $this->getSqliteForeignKeys($tableName2);

                foreach ($foreignKeys as $fk) {
                    if ($fk['referenced_table'] === $tableName) {
                        $dependentKeys[] = [
                            'dependent_table' => $tableName2,
                            'column_name' => $fk['column_name'],
                            'constraint_name' => $fk['constraint_name'],
                        ];
                    }
                }
            }

            return $dependentKeys;
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Format foreign keys for display.
     */
    public function formatForeignKeysForDisplay(Collection $foreignKeys): array
    {
        return $foreignKeys->map(fn ($fk) => [
            $fk['constraint_name'] ?? 'N/A',
            $fk['column_name'] ?? 'N/A',
            $fk['referenced_table'] ?? 'N/A',
            $fk['referenced_column'] ?? 'N/A',
        ])->toArray();
    }

    /**
     * Format dependent keys for display.
     */
    public function formatDependentKeysForDisplay(Collection $dependentKeys): array
    {
        return $dependentKeys->map(fn ($fk) => [
            $fk['dependent_table'] ?? 'N/A',
            $fk['column_name'] ?? 'N/A',
            $fk['constraint_name'] ?? 'N/A',
        ])->toArray();
    }
}
