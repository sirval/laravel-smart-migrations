<?php

namespace Sirval\LaravelSmartMigrations\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Sirval\LaravelSmartMigrations\LaravelSmartMigrations
 *
 * @method static \Illuminate\Support\Collection rollbackTable(string $table, array $options = [])
 * @method static \Illuminate\Support\Collection rollbackModel(string $model, array $options = [])
 * @method static \Illuminate\Support\Collection rollbackBatch(int $batch, array $options = [])
 * @method static \Illuminate\Support\Collection listMigrationsForTable(string $table)
 * @method static \Illuminate\Support\Collection listMigrationsForModel(string $model)
 * @method static array getTableStatus(string $table)
 * @method static array getModelStatus(string $model)
 */
class SmartMigrations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sirval\LaravelSmartMigrations\LaravelSmartMigrations::class;
    }
}
