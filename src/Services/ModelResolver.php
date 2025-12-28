<?php

namespace Sirval\LaravelSmartMigrations\Services;

use Illuminate\Database\Eloquent\Model;
use Sirval\LaravelSmartMigrations\Exceptions\ModelNotFoundException;

class ModelResolver
{
    /**
     * Constructor.
     *
     * @param  string  $defaultNamespace  Default namespace for models (e.g., App\Models)
     */
    public function __construct(
        private string $defaultNamespace = 'App\\Models'
    ) {}

    /**
     * Resolve a model name or class to its database table name.
     *
     * @param  string  $model  Model class name or full namespace
     * @return string The database table name
     *
     * @throws ModelNotFoundException
     */
    public function resolveTableFromModel(string $model): string
    {
        $className = $this->buildFullClassName($model);

        if (! $this->validateModelExists($className)) {
            throw ModelNotFoundException::notFound($model);
        }

        /** @var Model $instance */
        $instance = new $className;

        return $instance->getTable();
    }

    /**
     * Validate that a model class exists and is a valid Eloquent model.
     *
     * @param  string  $className  Full class namespace
     */
    public function validateModelExists(string $className): bool
    {
        try {
            if (! class_exists($className)) {
                return false;
            }

            // Check if it's a subclass of Eloquent Model
            if (! is_subclass_of($className, Model::class)) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Build the full class name from a model reference.
     *
     * If the model name contains a backslash, assume it's a full namespace.
     * Otherwise, prepend the default namespace.
     *
     * @param  string  $model  Model name or namespace
     * @return string Full class namespace
     */
    public function buildFullClassName(string $model): string
    {
        // If already a full namespace (contains backslash), return as-is
        if (strpos($model, '\\') !== false) {
            return $model;
        }

        // Otherwise, prepend the default namespace
        return $this->defaultNamespace.'\\'.$model;
    }

    /**
     * Get the configured model namespace.
     */
    public function getNamespace(): string
    {
        return $this->defaultNamespace;
    }

    /**
     * Set a custom model namespace.
     *
     * @return $this
     */
    public function setNamespace(string $namespace): self
    {
        $this->defaultNamespace = $namespace;

        return $this;
    }
}
