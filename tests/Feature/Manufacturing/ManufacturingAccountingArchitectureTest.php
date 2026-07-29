<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps production manufacturing accounting on the value entry orchestrator path', function (): void {
    $source = File::get(app_path('Services/Manufacturing/ProductionOrderService.php'));

    expect($source)->not->toContain('createWipGlEntries')
        ->and($source)->not->toContain('createCapacityGlEntries')
        ->and($source)->not->toContain('createFinishGlEntries')
        ->and($source)->not->toContain('createVarianceGlEntries')
        ->and($source)->not->toContain('createGlEntry(')
        ->and($source)->toContain('ValueEntryAccountingOrchestrator')
        ->and($source)->toContain('ProductionOrderCostSettlementService');
});

it('does not let manufacturing services directly create inventory costing gl entries', function (): void {
    $forbiddenPatterns = [
        '->createGlEntry(',
        'GlEntry::create(',
        'GeneralLedgerPostingKernel',
        '->postTransaction(',
    ];

    $allowedFiles = [
        app_path('Services/Manufacturing/CapExProjectService.php'),
    ];

    $violations = collect(File::allFiles(app_path('Services/Manufacturing')))
        ->reject(fn (SplFileInfo $file): bool => in_array($file->getPathname(), $allowedFiles, true))
        ->flatMap(function (SplFileInfo $file) use ($forbiddenPatterns): array {
            $source = File::get($file->getPathname());

            return collect($forbiddenPatterns)
                ->filter(fn (string $pattern): bool => str_contains($source, $pattern))
                ->map(fn (string $pattern): string => $file->getRelativePathname().': '.$pattern)
                ->all();
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});

it('keeps shop floor and production journal posting off direct gl helpers', function (): void {
    $productionJournalRoutine = File::get(app_path('Services/Posting/ProductionJournalPostingRoutine.php'));
    $shopFloorService = File::get(app_path('Services/Manufacturing/ProductionOperationExecutionService.php'));
    $shopFloorResource = File::get(app_path('Filament/Resources/ProductionOperationExecutions/ProductionOperationExecutionResource.php'));

    expect($productionJournalRoutine)
        ->not->toContain('createGeneralLedgerEntry')
        ->toContain('ValueEntryAccountingOrchestrator')
        ->and($shopFloorService)
        ->not->toContain('GlEntry::create')
        ->not->toContain('createGeneralLedgerEntry')
        ->not->toContain('PostingService::class')
        ->and($shopFloorResource)
        ->not->toContain('GlEntry::create')
        ->not->toContain('createGeneralLedgerEntry')
        ->not->toContain('PostingService::class');
});

it('keeps manufacturing models and observers from posting accounting directly', function (): void {
    $forbiddenPatterns = [
        'GlEntry::create(',
        '->createGlEntry(',
        'createGeneralLedgerEntry',
        'GeneralLedgerPostingKernel',
        '->postTransaction(',
    ];

    $paths = [
        ...collect(File::allFiles(app_path('Models/Manufacturing')))->all(),
        ...collect(File::allFiles(app_path('Observers')))->filter(fn (SplFileInfo $file): bool => str_contains(File::get($file->getPathname()), 'Production'))->all(),
    ];

    $violations = collect($paths)
        ->flatMap(function (SplFileInfo $file) use ($forbiddenPatterns): array {
            $source = File::get($file->getPathname());

            return collect($forbiddenPatterns)
                ->filter(fn (string $pattern): bool => str_contains($source, $pattern))
                ->map(fn (string $pattern): string => $file->getRelativePathname().': '.$pattern)
                ->all();
        })
        ->values()
        ->all();

    expect($violations)->toBeEmpty();
});
