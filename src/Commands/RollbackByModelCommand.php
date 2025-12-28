<?php

namespace Sirval\LaravelSmartMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Sirval\LaravelSmartMigrations\Exceptions\ModelNotFoundException;
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
    protected $signature = 'migrate:rollback-model {models* : The model name(s) to rollback (comma or space separated)}
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
            $modelInput = $this->argument('models');

            // Parse input: could be "User,Post,Comment" or "User Post Comment"
            $models = $this->parseModelInput($modelInput);

            if (empty($models)) {
                $this->error('No models provided.');

                return self::FAILURE;
            }

            // Collect migrations for all models
            $allMigrations = collect();
            $notFoundModels = [];

            foreach ($models as $modelName) {
                $this->info("Resolving model: <fg=cyan>{$modelName}</>");

                try {
                    // Validate and resolve model to table
                    if (! $this->resolver->validateModelExists($this->resolver->buildFullClassName($modelName))) {
                        throw ModelNotFoundException::notFound($modelName);
                    }

                    $table = $this->resolver->resolveTableFromModel($modelName);
                    $this->info("Model <fg=cyan>{$modelName}</> resolves to table: <fg=green>{$table}</>");

                    // Find migrations for the table
                    $migrations = $this->finder->findByTable($table);

                    if ($migrations->isEmpty()) {
                        $notFoundModels[] = $modelName;
                    } else {
                        $this->outputMigrationsSummary($migrations, $modelName);
                        $allMigrations = $allMigrations->merge($migrations);
                    }
                } catch (ModelNotFoundException) {
                    $notFoundModels[] = $modelName;
                }
            }

            // Report models with no migrations
            if (! empty($notFoundModels)) {
                $this->warn('No migrations found for: '.implode(', ', $notFoundModels));
            }

            if ($allMigrations->isEmpty()) {
                $this->error('No migrations found for any of the specified models.');

                return self::FAILURE;
            }

            // Handle options
            if ($this->option('latest')) {
                $allMigrations = $this->getLatestMigrations($allMigrations);
            } elseif ($this->option('oldest')) {
                $allMigrations = $this->getOldestMigrations($allMigrations);
            } elseif ($batch = $this->option('batch')) {
                $allMigrations = $allMigrations->filter(fn ($m) => $m->batch === (int) $batch);
            } elseif (! $this->option('all')) {
                // Default: only rollback current batch
                $allMigrations = $this->getCurrentBatchMigrations($allMigrations);
            }

            if ($allMigrations->isEmpty()) {
                $this->warn('No migrations matched the specified criteria.');

                return self::SUCCESS;
            }

            // Validate before rollback
            if (! $this->rollbacker->validateBeforeRollback($allMigrations, $this->option('all'))) {
                $this->error('Validation failed: Cannot safely rollback these migrations.');

                return self::FAILURE;
            }

            $this->outputRollbackPlan($allMigrations);

            // Ask for confirmation unless forced
            if (! $this->option('force') && ! $this->confirm('Do you want to rollback these migrations?', false)) {
                $this->info('Rollback cancelled.');

                return self::SUCCESS;
            }

            // Execute rollback
            $this->line('');
            $this->info('Rolling back migrations...');

            $this->rollbacker->rollbackMultiple($allMigrations);

            $this->line('');
            $this->info("<fg=green>✓</> Successfully rolled back <fg=cyan>{$allMigrations->count()}</> migration(s).");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('An error occurred: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Parse model input (comma or space separated).
     */
    private function parseModelInput(array $input): array
    {
        if (empty($input)) {
            return [];
        }

        $models = [];
        foreach ($input as $item) {
            // Split by comma if needed
            foreach (explode(',', $item) as $model) {
                $model = trim($model);
                if (! empty($model)) {
                    $models[] = $model;
                }
            }
        }

        return array_unique($models);
    }

    /**
     * Get only the latest migration for each model/table.
     */
    private function getLatestMigrations(Collection $migrations): Collection
    {
        // Get latest migration overall (highest batch)
        return collect([$migrations->sortBy('batch')->last()]);
    }

    /**
     * Get only the oldest migration for each model/table.
     */
    private function getOldestMigrations(Collection $migrations): Collection
    {
        // Get oldest migration overall (lowest batch)
        return collect([$migrations->sortBy('batch')->first()]);
    }

    /**
     * Output a summary of found migrations.
     */
    private function outputMigrationsSummary(Collection $migrations, string $model = ''): void
    {
        $batches = $this->rollbacker->getExecutedBatches($migrations);
        $batchString = implode(', ', $batches);

        $this->line('');
        $modelLabel = $model ? "Model: <fg=cyan>{$model}</>" : '';
        if ($modelLabel) {
            $this->info($modelLabel);
        }

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
     * Get migrations from the current (highest) batch.
     */
    private function getCurrentBatchMigrations(Collection $migrations): Collection
    {
        $maxBatch = $migrations->max('batch');

        return $migrations->filter(fn ($m) => $m->batch === $maxBatch);
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
