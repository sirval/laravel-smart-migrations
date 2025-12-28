<?php

namespace Sirval\LaravelSmartMigrations\Exceptions;

use Exception;

class NoMigrationsFoundException extends Exception
{
    public static function forTable(string $table): self
    {
        return new self("No migrations found for table '{$table}'.");
    }

    public static function forModel(string $model): self
    {
        return new self("No migrations found for model '{$model}'.");
    }

    public static function forBatch(int $batch): self
    {
        return new self("No migrations found for batch {$batch}.");
    }

    public static function generic(string $message = ''): self
    {
        return new self($message ?: 'No migrations found matching the criteria.');
    }
}
