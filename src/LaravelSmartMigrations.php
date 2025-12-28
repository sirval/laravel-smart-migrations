<?php

namespace Sirval\LaravelSmartMigrations;

use Sirval\LaravelSmartMigrations\Services\SmartMigrations;

/**
 * Laravel Smart Migrations
 *
 * Main class for the package. Acts as a container for
 * the SmartMigrations service and is registered in the facade.
 *
 * @mixin SmartMigrations
 */
class LaravelSmartMigrations
{
    public function __construct(private SmartMigrations $smartMigrations)
    {
    }

    /**
     * Get the SmartMigrations service instance.
     */
    public function getSmartMigrations(): SmartMigrations
    {
        return $this->smartMigrations;
    }

    /**
     * Delegate method calls to the SmartMigrations service.
     */
    public function __call(string $method, array $arguments)
    {
        return $this->smartMigrations->{$method}(...$arguments);
    }
}
