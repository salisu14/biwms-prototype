<?php

use Tests\CreatesFinancialDocumentFixtures;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Deterministic test environment
|--------------------------------------------------------------------------
|
| The host shell may export application env vars (e.g. APP_ENV=local,
| CACHE_STORE=database, SESSION_DRIVER=database, QUEUE_CONNECTION=database).
| Laravel's immutable env repository reads $_SERVER/$_ENV before getenv, and
| PHPUnit's <env> only sets getenv unless force is used, so ambient values can
| leak into tests and defeat the phpunit.xml isolation settings. Force the
| test-only values into all three sources before any application boots.
|
*/

foreach ([
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(CreatesFinancialDocumentFixtures::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
