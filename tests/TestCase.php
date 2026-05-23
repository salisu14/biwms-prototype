<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private static bool $hasValidatedTestDatabaseIsolation = false;

    protected function setUp(): void
    {
        parent::setUp();

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

        $databaseName = strtolower($database);
        $looksLikeTestDb = str_contains($databaseName, 'test');

        if (! $looksLikeTestDb) {
            throw new RuntimeException(
                "Unsafe test database '{$database}' for connection '{$connection}'. ".
                'Use a dedicated testing database name or set ALLOW_NON_ISOLATED_TEST_DB=true explicitly.'
            );
        }
    }
}
