<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has the required opening inventory lines table after migrations', function (): void {
    expect(Schema::hasTable('opening_inventory_lines'))->toBeTrue();
});

it('reports missing opening inventory schema instead of crashing', function (): void {
    Schema::dropIfExists('opening_inventory_lines');

    $exitCode = Artisan::call('biwms:inventory-reconcile', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($report['schema_findings'][0]['classification'])->toBe('SCHEMA_INCOMPLETE')
        ->and($report['schema_findings'][0]['missing_table'])->toBe('opening_inventory_lines')
        ->and($report['schema_findings'][0]['expected_migration'])->toBe('database/migrations/2026_07_19_132703_create_opening_inventory_lines_table.php');
});
