<?php

namespace Sirval\LaravelSmartMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Sirval\LaravelSmartMigrations\Exceptions\NoMigrationsFoundException;
use Sirval\LaravelSmartMigrations\Services\MigrationFinder;
use Sirval\LaravelSmartMigrations\Services\MigrationParser;
use Sirval\LaravelSmartMigrations\Services\MigrationRollbacker;

class RollbackByTableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:rollback-table {tables* : The table name(s) to rollback (comma or space separated)}
                            {--latest : Only rollback the latest migration for this table}
                            {--oldest : Only rollback the oldest migration for this table}
                            {--batch= : Only rollback migrations from a specific batch}
                            {--all : Rollback all migrations without batch checks}
                            {--force : Skip confirmation prompts}
                            {--interactive : Show options and let user choose}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback all migrations for a specific database table';

    /**
     * Create a new command instance.
     */
    public function __construct(
        public MigrationFinder $finder,
        public MigrationParser $parser,
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
            $tableInput = $this->argument('tables');

            // Parse input: could be "users,posts,comments" or "users posts comments"
            $tables = $this->parseTableInput($tableInput);

            if (empty($tables)) {
                $this->error('No tables provided.');

                return self::FAILURE;
            }

            // Collect migrations for all tables
            $allMigrations = collect();
            $notFoundTables = [];

            foreach ($tables as $table) {
                $this->info("Searching for migrations matching table: <fg=cyan>{$table}</>");

                try {
                    $migrations = $this->finder->findByTable($table);
                    if ($migrations->isEmpty()) {
                        $notFoundTables[] = $table;
                    } else {
                        $this->outputMigrationsSummary($migrations, $table);
                        $allMigrations = $allMigrations->merge($migrations);
                    }
                } catch (NoMigrationsFoundException) {
                    $notFoundTables[] = $table;
                }
            }

            // Report tables with no migrations
            if (! empty($notFoundTables)) {
                $this->warn('No migrations found for: '.implode(', ', $notFoundTables));
            }

            if ($allMigrations->isEmpty()) {
                $this->error('No migrations found for any of the specified tables.');

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
     * Parse table input (comma or space separated).
     */
    private function parseTableInput(array $input): array
    {
        if (empty($input)) {
            return [];
        }

        $tables = [];
        foreach ($input as $item) {
            // Split by comma if needed
            foreach (explode(',', $item) as $table) {
                $table = trim($table);
                if (! empty($table)) {
                    $tables[] = $table;
                }
            }
        }

        return array_unique($tables);
    }

    /**
     * Get only the latest migration for each table.
     */
    private function getLatestMigrations(Collection $migrations): Collection
    {
        // Group by table and get latest from each
        return $migrations->groupBy(function ($m) {
            return $this->parser->parseTableFromMigrationName($m->migration);
        })->map(fn ($group) => $group->sortBy('batch')->last())
            ->values();
    }

    /**
     * Get only the oldest migration for each table.
     */
    private function getOldestMigrations(Collection $migrations): Collection
    {
        // Group by table and get oldest from each
        return $migrations->groupBy(function ($m) {
            return $this->parser->parseTableFromMigrationName($m->migration);
        })->map(fn ($group) => $group->sortBy('batch')->first())
            ->values();
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
    private function outputMigrationsSummary(Collection $migrations, string $table = ''): void
    {
        $batches = $this->rollbacker->getExecutedBatches($migrations);
        $batchString = implode(', ', $batches);

        $this->line('');
        $tableLabel = $table ? "Table: <fg=cyan>{$table}</>" : '';
        if ($tableLabel) {
            $this->info($tableLabel);
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
