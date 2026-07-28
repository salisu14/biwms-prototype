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
