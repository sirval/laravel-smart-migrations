<?php

namespace Sirval\LaravelSmartMigrations\Services;

use Illuminate\Support\Str;

class MigrationParser
{
    /**
     * Extract table name from a migration file.
     *
     * Parses the migration file to find Schema::create or Schema::table calls
     * and extracts the table name from them.
     *
     * @param  string  $filePath  Full path to the migration file
     * @return string|null  The table name or null if not found
     */
    public function parseTableFromMigrationFile(string $filePath): ?string
    {
        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        // Try to match Schema::create('table_name', ...)
        if (preg_match("/Schema::create\s*\(\s*['\"](\w+)['\"]/", $content, $matches)) {
            return $matches[1];
        }

        // Try to match Schema::table('table_name', ...)
        if (preg_match("/Schema::table\s*\(\s*['\"](\w+)['\"]/", $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract table name from a migration class name.
     *
     * Uses heuristics to guess table names from class names.
     * Examples:
     *   - CreateUsersTable → users
     *   - AddEmailToUsersTable → users
     *   - CreateUserProfilesTable → user_profiles
     *   - AddTimestampsToPostsTable → posts
     *
     * @param  string  $className  The migration class name
     * @return string|null  The estimated table name
     */
    public function parseTableFromClassName(string $className): ?string
    {
        // Remove "class" suffix if present
        $name = preg_replace('/^class\s+/', '', $className);

        // Remove trailing "Table" suffix for consistent processing
        $cleanName = $name;
        if (str_ends_with($cleanName, 'Table')) {
            $cleanName = substr($cleanName, 0, -5);
        }

        // Try "AddXToY" pattern - extract Y
        if (preg_match('/To(\w+)$/', $cleanName, $matches)) {
            return $this->camelToSnake($matches[1]);
        }

        // Extract from CreateX, AlterX, ModifyX, UpdateX patterns
        if (preg_match('/^(?:Create|Alter|Modify|Update)(\w+)$/', $cleanName, $matches)) {
            return $this->camelToSnake($matches[1]);
        }

        // Fallback: convert entire class name to snake_case
        return $this->camelToSnake($cleanName);
    }

    /**
     * Extract table name from a migration filename.
     *
     * Parses the filename (without timestamp prefix) to guess the table name.
     * Examples:
     *   - 2024_01_01_000001_create_users_table → users
     *   - 2024_01_01_000001_add_email_to_users_table → users
     *   - 2024_01_01_000001_create_user_profiles → user_profiles
     *
     * @param  string  $migrationName  The migration filename
     * @return string|null  The estimated table name
     */
    public function parseTableFromMigrationName(string $migrationName): ?string
    {
        // Remove timestamp prefix
        $withoutTimestamp = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migrationName);

        // Remove common suffixes
        $cleaned = preg_replace('/_table$/', '', $withoutTimestamp);

        // Extract from "add_column_to_users"
        if (preg_match('/to_(\w+)$/', $cleaned, $matches)) {
            return $matches[1];
        }

        // Extract from "create_users"
        if (preg_match('/^(?:create_|add_|alter_|modify_|update_)?(\w+)$/', $cleaned, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract model name from a full namespace.
     *
     * Examples:
     *   - App\Models\User → User
     *   - App\Models\UserProfile → UserProfile
     *   - Modules\Blog\Models\Post → Post
     *
     * @param  string  $namespace  The full namespace
     * @return string|null  The model class name
     */
    public function extractModelFromNamespace(string $namespace): ?string
    {
        return class_basename($namespace);
    }

    /**
     * Convert a camelCase string to snake_case.
     *
     * @param  string  $string
     * @return string
     */
    private function camelToSnake(string $string): string
    {
        return Str::snake($string);
    }
}
