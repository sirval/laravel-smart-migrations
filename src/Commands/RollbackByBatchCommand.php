<?php

namespace Sirval\LaravelSmartMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Sirval\LaravelSmartMigrations\Exceptions\NoMigrationsFoundException;
use Sirval\LaravelSmartMigrations\Services\MigrationFinder;
use Sirval\LaravelSmartMigrations\Services\MigrationRollbacker;

class RollbackByBatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:rollback-batch {batch : The batch number to rollback}
                            {--show : Only show migrations without rolling back}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback all migrations from a specific batch';

    /**
     * Create a new command instance.
     */
    public function __construct(
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
            $batch = (int) $this->argument('batch');

            $this->info("Searching for migrations in batch: <fg=cyan>{$batch}</>");

            // Find migrations for the batch
            $migrations = $this->finder->findByBatch($batch);

            if ($migrations->isEmpty()) {
                $this->warn("No migrations found in batch {$batch}.");
                return self::SUCCESS;
            }

            $this->outputMigrationsSummary($migrations);

            // If only showing, return here
            if ($this->option('show')) {
                $this->info('Use without --show flag to rollback these migrations.');
                return self::SUCCESS;
            }

            // Validate before rollback
            if (! $this->rollbacker->validateBeforeRollback($migrations)) {
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
            $this->info("<fg=green>✓</> Successfully rolled back <fg=cyan>{$migrations->count()}</> migration(s) from batch <fg=cyan>{$batch}</>");

            return self::SUCCESS;
        } catch (NoMigrationsFoundException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('An error occurred: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Output a summary of found migrations.
     */
    private function outputMigrationsSummary(Collection $migrations): void
    {
        $this->line('');
        $this->table(
            ['Migration', 'Status'],
            $migrations->map(fn ($m) => [
                $m->migration,
                '✓ Executed',
            ])->toArray()
        );
        $this->line('');
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
            ['Migration'],
            $migrations->map(fn ($m) => [$m->migration])->toArray()
        );
    }
}
