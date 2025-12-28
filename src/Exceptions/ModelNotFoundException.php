<?php

namespace Sirval\LaravelSmartMigrations\Exceptions;

use Exception;

class ModelNotFoundException extends Exception
{
    public static function notFound(string $model): self
    {
        return new self("Model '{$model}' not found. Check your model namespace in config('smart-migrations.model_namespace').");
    }

    public static function classDoesNotExist(string $class): self
    {
        return new self("Class '{$class}' does not exist.");
    }

    public static function notAModel(string $class): self
    {
        return new self("Class '{$class}' is not a valid Eloquent model.");
    }
}
