<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private static bool $hasValidatedTestDatabaseIsolation = false;

    private static ?string $postgresRuntimeTestingSchema = null;

    protected function setUp(): void
    {
        parent::setUp();

        if ((string) config('database.default') === 'pgsql') {
            if (self::$postgresRuntimeTestingSchema !== null) {
                config()->set('database.connections.pgsql.search_path', self::$postgresRuntimeTestingSchema);
            }

            $this->applyPostgresSearchPath();
        }

        if (self::$hasValidatedTestDatabaseIsolation) {
            return;
        }

        self::$hasValidatedTestDatabaseIsolation = true;

        $allowNonIsolated = filter_var(
            (string) env('ALLOW_NON_ISOLATED_TEST_DB', false),
            FILTER_VALIDATE_BOOL
        );

        if ($allowNonIsolated) {
            return;
        }

        $appEnv = (string) config('app.env');
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($appEnv !== 'testing') {
            throw new RuntimeException(
                "Unsafe test environment detected. APP_ENV must be 'testing' for automated tests. ".
                "Current APP_ENV='{$appEnv}', DB_CONNECTION='{$connection}', DB_DATABASE='{$database}'."
            );
        }

        if ($connection === 'sqlite') {
            $isIsolatedSqlite = $database === ':memory:' || str_ends_with($database, 'testing.sqlite');

            if (! $isIsolatedSqlite) {
                throw new RuntimeException(
                    "Unsafe sqlite test database '{$database}'. ".
                    "Use ':memory:' or a dedicated 'testing.sqlite' file."
                );
            }

            return;
        }

        if ($connection === 'pgsql') {
            $schema = (string) config('database.connections.pgsql.search_path');

            if (str_contains(strtolower($schema), 'test')) {
                return;
            }
        }

        $databaseName = strtolower($database);
        $looksLikeTestDb = str_contains($databaseName, 'test');

        if (! $looksLikeTestDb) {
            throw new RuntimeException(
                "Unsafe test database '{$database}' for connection '{$connection}'. ".
                'Use a dedicated testing database name or set ALLOW_NON_ISOLATED_TEST_DB=true explicitly.'
            );
        }
    }

    protected function beforeRefreshingDatabase()
    {
        $this->ensurePostgresTestingSchemaExists();
    }

    protected function setUpTraits()
    {
        $uses = $this->traitsUsedByTest ?? array_flip(class_uses_recursive(static::class));

        if ((string) config('database.default') === 'pgsql' && isset($uses[RefreshDatabase::class])) {
            unset($uses[RefreshDatabase::class]);

            $this->traitsUsedByTest = $uses;

            $setUp = parent::setUpTraits();

            $this->refreshPostgresDatabaseForRefreshDatabaseTrait();

            return [RefreshDatabase::class => true] + $setUp;
        }

        return parent::setUpTraits();
    }

    protected function migrateFreshUsing()
    {
        $parameters = [
            '--drop-views' => false,
            '--drop-types' => false,
            '--seed' => false,
        ];

        if ((string) config('database.default') !== 'pgsql') {
            return $parameters;
        }

        return array_merge($parameters, [
            '--schema-path' => $this->testing_schema_dump_path(),
            '--drop-types' => true,
        ]);
    }

    private function testing_schema_dump_path(): string
    {
        $schema = (string) config('database.connections.pgsql.search_path');
        $sourcePath = database_path('schema/pgsql-schema.sql');
        $targetDirectory = storage_path('framework/testing');
        $targetPath = $targetDirectory.'/pgsql-schema-'.$schema.'.sql';

        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $source = file_get_contents($sourcePath);

        if ($source === false) {
            throw new RuntimeException("Unable to read schema dump at {$sourcePath}.");
        }

        $schemaDump = str_replace('public.', "{$schema}.", $source);
        file_put_contents($targetPath, $schemaDump);

        return $targetPath;
    }

    protected function refreshPostgresTestingDatabase(): void
    {
        if ((string) config('database.default') !== 'pgsql') {
            $this->artisan('migrate:fresh');

            return;
        }

        $this->useFreshPostgresRuntimeTestingSchema();

        $this->artisan('migrate:fresh', [
            '--schema-path' => $this->testing_schema_dump_path(),
            '--drop-types' => true,
        ]);

        $this->setPostgresSearchPathFromConfig();
    }

    private function refreshPostgresDatabaseForRefreshDatabaseTrait(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->refreshPostgresTestingDatabase();

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        if (method_exists($this, 'beginDatabaseTransaction')) {
            $this->beginDatabaseTransaction();
        }
    }

    private function ensurePostgresTestingSchemaExists(): void
    {
        if ((string) config('database.default') !== 'pgsql') {
            return;
        }

        $schema = (string) config('database.connections.pgsql.search_path');

        DB::statement(sprintf('CREATE SCHEMA IF NOT EXISTS "%s"', str_replace('"', '""', $schema)));
    }

    private function useFreshPostgresRuntimeTestingSchema(): void
    {
        $schema = (string) env('DB_SCHEMA', config('database.connections.pgsql.search_path'));

        if (! str_contains(strtolower($schema), 'test')) {
            throw new RuntimeException(
                "Refusing to prepare non-test PostgreSQL schema '{$schema}'."
            );
        }

        $schemaPrefix = substr($schema, 0, 42);
        $runtimeSchema = sprintf(
            '%s_%s_%s',
            $schemaPrefix,
            getmypid(),
            substr(md5((string) microtime(true)), 0, 8)
        );
        self::$postgresRuntimeTestingSchema = $runtimeSchema;

        $quotedSchema = str_replace('"', '""', $runtimeSchema);

        DB::statement(sprintf('CREATE SCHEMA IF NOT EXISTS "%s"', $quotedSchema));

        config()->set('database.connections.pgsql.search_path', $runtimeSchema);

        DB::purge('pgsql');
        DB::reconnect('pgsql');
        $this->setPostgresSearchPathFromConfig();
    }

    private function setPostgresSearchPathFromConfig(): void
    {
        $schema = (string) config('database.connections.pgsql.search_path');
        $quotedSchema = str_replace('"', '""', $schema);

        DB::purge('pgsql');
        DB::reconnect('pgsql');
        $this->applyPostgresSearchPath();
    }

    private function applyPostgresSearchPath(): void
    {
        $schema = (string) config('database.connections.pgsql.search_path');
        $quotedSchema = str_replace('"', '""', $schema);

        DB::statement(sprintf('SET search_path TO "%s"', $quotedSchema));
    }
}
