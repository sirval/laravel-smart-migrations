<?php

namespace Sirval\LaravelSmartMigrations;

use Sirval\LaravelSmartMigrations\Commands\ListModelMigrationsCommand;
use Sirval\LaravelSmartMigrations\Commands\ListTableMigrationsCommand;
use Sirval\LaravelSmartMigrations\Commands\RollbackByBatchCommand;
use Sirval\LaravelSmartMigrations\Commands\RollbackByModelCommand;
use Sirval\LaravelSmartMigrations\Commands\RollbackByTableCommand;
use Sirval\LaravelSmartMigrations\Services\MigrationFinder;
use Sirval\LaravelSmartMigrations\Services\MigrationParser;
use Sirval\LaravelSmartMigrations\Services\MigrationRollbacker;
use Sirval\LaravelSmartMigrations\Services\ModelResolver;
use Sirval\LaravelSmartMigrations\Services\SmartMigrations;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelSmartMigrationsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-smart-migrations')
            ->hasConfigFile('smart-migrations')
            ->hasViews()
            ->hasMigration('create_smart_migrations_table')
            ->hasCommand(RollbackByTableCommand::class)
            ->hasCommand(RollbackByModelCommand::class)
            ->hasCommand(RollbackByBatchCommand::class)
            ->hasCommand(ListTableMigrationsCommand::class)
            ->hasCommand(ListModelMigrationsCommand::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Publish config file
        $this->publishes([
            __DIR__.'/../config/smart-migrations.php' => config_path('smart-migrations.php'),
        ], 'laravel-smart-migrations-config');

        // Publish migration file with dynamic timestamp
        $migrationPath = database_path('migrations/'.date('Y_m_d_His').'_create_smart_migrations_table.php');
        $this->publishes([
            __DIR__.'/../database/migrations/create_smart_migrations_table.php.stub' => $migrationPath,
        ], 'laravel-smart-migrations-migrations');
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(MigrationFinder::class, function ($app) {
            return new MigrationFinder(
                $app['db'],
                'migrations'
            );
        });

        $this->app->singleton(MigrationParser::class, function () {
            return new MigrationParser;
        });

        $this->app->singleton(ModelResolver::class, function () {
            return new ModelResolver(
                config('smart-migrations.model_namespace', 'App\\Models')
            );
        });

        $this->app->singleton(MigrationRollbacker::class, function ($app) {
            return new MigrationRollbacker(
                $app['db'],
                'migrations'
            );
        });

        // Register the SmartMigrations service
        $this->app->singleton(SmartMigrations::class, function ($app) {
            return new SmartMigrations(
                $app->make(MigrationFinder::class),
                $app->make(MigrationParser::class),
                $app->make(MigrationRollbacker::class),
                $app->make(ModelResolver::class),
            );
        });

        // Register the main LaravelSmartMigrations class
        $this->app->singleton(LaravelSmartMigrations::class, function ($app) {
            return new LaravelSmartMigrations(
                $app->make(SmartMigrations::class)
            );
        });
    }
}
