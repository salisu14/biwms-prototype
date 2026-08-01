<?php

declare(strict_types=1);

use App\Services\Manufacturing\ProductionChildOrderGenerationService;
use App\Services\Manufacturing\ProductionHierarchyExplosionService;
use Illuminate\Support\Facades\File;

it('keeps phase 2a point 1 as schema and domain foundation only', function (): void {
    $forbiddenRuntimePatterns = [
        'explodeHierarchy(',
        'generateChildOrders(',
        'createReservationsForHierarchy(',
        'releaseReservations(',
        'postHierarchyCost(',
        'settleHierarchyCost(',
    ];

    $paths = collect([
        app_path('Models/Manufacturing/ProductionHierarchy.php'),
        app_path('Models/Manufacturing/ProductionHierarchyNode.php'),
        app_path('Models/Manufacturing/ProductionOrderSupplyLink.php'),
        app_path('Models/Manufacturing/ProductionMaterialReservation.php'),
    ]);

    $violations = $paths
        ->flatMap(function (string $path) use ($forbiddenRuntimePatterns): array {
            $source = File::get($path);

            return collect($forbiddenRuntimePatterns)
                ->filter(fn (string $pattern): bool => str_contains($source, $pattern))
                ->map(fn (string $pattern): string => basename($path).': '.$pattern)
                ->all();
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty()
        ->and(class_exists(ProductionHierarchyExplosionService::class))->toBeFalse()
        ->and(class_exists(ProductionChildOrderGenerationService::class))->toBeFalse();
});

it('keeps new phase 2a manufacturing models away from direct inventory value or gl posting', function (): void {
    $forbiddenPatterns = [
        'GlEntry::create(',
        'ValueEntry::create(',
        'ItemLedgerEntry::create(',
        'CapacityLedgerEntry::create(',
        'GeneralLedgerPostingKernel',
        'ValueEntryAccountingOrchestrator',
        '->postTransaction(',
    ];

    $paths = [
        app_path('Models/Manufacturing/ProductionHierarchy.php'),
        app_path('Models/Manufacturing/ProductionHierarchyNode.php'),
        app_path('Models/Manufacturing/ProductionOrderSupplyLink.php'),
        app_path('Models/Manufacturing/ProductionMaterialReservation.php'),
    ];

    $violations = collect($paths)
        ->flatMap(function (string $path) use ($forbiddenPatterns): array {
            $source = File::get($path);

            return collect($forbiddenPatterns)
                ->filter(fn (string $pattern): bool => str_contains($source, $pattern))
                ->map(fn (string $pattern): string => basename($path).': '.$pattern)
                ->all();
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('does not introduce phase 2a runtime filament resources yet', function (): void {
    $resourcePaths = [
        app_path('Filament/Resources/ProductionHierarchies'),
        app_path('Filament/Resources/ProductionOrderSupplyLinks'),
        app_path('Filament/Resources/ProductionMaterialReservations'),
    ];

    expect(collect($resourcePaths)->filter(fn (string $path): bool => File::exists($path))->values()->all())->toBeEmpty();
});
