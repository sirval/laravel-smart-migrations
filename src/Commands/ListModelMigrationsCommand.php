<?php

namespace Sirval\LaravelSmartMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Sirval\LaravelSmartMigrations\Exceptions\ModelNotFoundException;
use Sirval\LaravelSmartMigrations\Exceptions\NoMigrationsFoundException;
use Sirval\LaravelSmartMigrations\Services\MigrationFinder;
use Sirval\LaravelSmartMigrations\Services\ModelResolver;

class ListModelMigrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:list-model-migrations {model : The model name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all migrations for a specific Eloquent model';

    /**
     * Create a new command instance.
     */
    public function __construct(
        public ModelResolver $resolver,
        public MigrationFinder $finder,
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

            $this->outputMigrations($migrations);

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
     * Output migrations in a table format.
     */
    private function outputMigrations(Collection $migrations): void
    {
        $this->line('');
        $this->table(
            ['#', 'Batch', 'Migration', 'Status'],
            $migrations->map(function ($migration, $index) {
                return [
                    $index + 1,
                    $migration->batch,
                    $migration->migration,
                    '✓ Executed',
                ];
            })->toArray()
        );
        $this->line('');

        $this->info("Total: <fg=cyan>{$migrations->count()}</> migration(s) found.");
    }
}
