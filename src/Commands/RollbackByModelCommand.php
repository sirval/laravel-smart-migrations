<?php

namespace Sirval\LaravelSmartMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Sirval\LaravelSmartMigrations\Exceptions\ModelNotFoundException;
use Sirval\LaravelSmartMigrations\Exceptions\NoMigrationsFoundException;
use Sirval\LaravelSmartMigrations\Services\MigrationFinder;
use Sirval\LaravelSmartMigrations\Services\MigrationRollbacker;
use Sirval\LaravelSmartMigrations\Services\ModelResolver;

class RollbackByModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:rollback-model {model : The model name to rollback}
                            {--latest : Only rollback the latest migration for this model}
                            {--oldest : Only rollback the oldest migration for this model}
                            {--batch= : Only rollback migrations from a specific batch}
                            {--all : Rollback all migrations without batch checks}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback all migrations for a specific Eloquent model';

    /**
     * Create a new command instance.
     */
    public function __construct(
        public ModelResolver $resolver,
        public MigrationFinder $finder,
        public MigrationRollbacker $rollbacker,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $modelName = $this->argument('model');

            $this->info("Resolving model: <fg=cyan>{$modelName}</>");

            // Validate and resolve model to table
            if (! $this->resolver->validateModelExists($this->resolver->buildFullClassName($modelName))) {
                throw ModelNotFoundException::notFound($modelName);
            }

            $table = $this->resolver->resolveTableFromModel($modelName);
            $this->info("Model <fg=cyan>{$modelName}</> resolves to table: <fg=green>{$table}</>");

            // Find migrations for the table
            $migrations = $this->finder->findByTable($table);

            if ($migrations->isEmpty()) {
                throw NoMigrationsFoundException::forTable($table);
            }

            $this->outputMigrationsSummary($migrations);

            // Handle options
            if ($this->option('latest')) {
                $migrations = $this->getLatestMigration($migrations);
            } elseif ($this->option('oldest')) {
                $migrations = $this->getOldestMigration($migrations);
            } elseif ($batch = $this->option('batch')) {
                $migrations = $migrations->filter(fn ($m) => $m->batch === (int) $batch);
            } elseif (! $this->option('all')) {
                // Default: only rollback current batch
                $migrations = $this->getCurrentBatchMigrations($migrations);
            }

            if ($migrations->isEmpty()) {
                $this->warn('No migrations matched the specified criteria.');

                return self::SUCCESS;
            }

            // Validate before rollback
            if (! $this->rollbacker->validateBeforeRollback($migrations, $this->option('all'))) {
                $this->error('Validation failed: Cannot safely rollback these migrations.');

                return self::FAILURE;
            }

            $this->outputRollbackPlan($migrations);

            // Ask for confirmation unless forced
            if (! $this->option('force') && ! $this->confirm('Do you want to rollback these migrations?', false)) {
                $this->info('Rollback cancelled.');

                return self::SUCCESS;
            }

            // Execute rollback
            $this->line('');
            $this->info('Rolling back migrations...');

            $this->rollbacker->rollbackMultiple($migrations);

            $this->line('');
            $this->info("<fg=green>✓</> Successfully rolled back <fg=cyan>{$migrations->count()}</> migration(s).");

            return self::SUCCESS;
        } catch (ModelNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (NoMigrationsFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('An error occurred: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Get only the latest migration.
     */
    private function getLatestMigration(Collection $migrations): Collection
    {
        return collect([$migrations->last()]);
    }

    /**
     * Get only the oldest migration.
     */
    private function getOldestMigration(Collection $migrations): Collection
    {
        return collect([$migrations->first()]);
    }

    /**
     * Get migrations from the current (highest) batch.
     */
    private function getCurrentBatchMigrations(Collection $migrations): Collection
    {
        $maxBatch = $migrations->max('batch');

        return $migrations->filter(fn ($m) => $m->batch === $maxBatch);
    }

    /**
     * Output a summary of found migrations.
     */
    private function outputMigrationsSummary(Collection $migrations): void
    {
        $batches = $this->rollbacker->getExecutedBatches($migrations);
        $batchString = implode(', ', $batches);

        $this->line('');
        $this->table(
            ['Batch', 'Migration', 'Status'],
            $migrations->map(fn ($m) => [
                $m->batch,
                $m->migration,
                '✓ Executed',
            ])->toArray()
        );
        $this->line('');

        if (count($batches) > 1) {
            $this->warn("⚠ Multiple batches detected: {$batchString}. Use --all flag to rollback all.");
        }
    }

    /**
     * Output what will be rolled back.
     */
    private function outputRollbackPlan(Collection $migrations): void
    {
        $this->line('');
        $this->info('Migrations to rollback:');
        $this->line('');

        $this->table(
            ['Migration', 'Batch'],
            $migrations->map(fn ($m) => [
                $m->migration,
                $m->batch,
            ])->toArray()
        );
    }
}
