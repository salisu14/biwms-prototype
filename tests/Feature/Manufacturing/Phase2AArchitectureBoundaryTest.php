<?php

declare(strict_types=1);

use App\Services\Manufacturing\ChildProductionOrderGenerationService;
use App\Services\Manufacturing\MultiLevelBomExplosionService;
use App\Services\Manufacturing\MultiLevelProductionPlanningService;
use App\Services\Manufacturing\ProductionHierarchyService;
use App\Services\Manufacturing\ProductionMaterialReservationService;
use Illuminate\Support\Facades\File;

it('keeps phase 2a runtime as planning-only services', function (): void {
    expect(class_exists(MultiLevelBomExplosionService::class))->toBeTrue()
        ->and(class_exists(ProductionHierarchyService::class))->toBeTrue()
        ->and(class_exists(ChildProductionOrderGenerationService::class))->toBeTrue()
        ->and(class_exists(ProductionMaterialReservationService::class))->toBeTrue()
        ->and(class_exists(MultiLevelProductionPlanningService::class))->toBeTrue();
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
        app_path('Services/Manufacturing/MultiLevelBomExplosionService.php'),
        app_path('Services/Manufacturing/ProductionHierarchyService.php'),
        app_path('Services/Manufacturing/ChildProductionOrderGenerationService.php'),
        app_path('Services/Manufacturing/ProductionMaterialReservationService.php'),
        app_path('Services/Manufacturing/MultiLevelProductionPlanningService.php'),
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
