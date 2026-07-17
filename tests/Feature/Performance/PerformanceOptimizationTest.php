<?php

declare(strict_types=1);

use App\Filament\Resources\AttendanceLedgerEntries\AttendanceLedgerEntryResource;
use App\Filament\Resources\EmployeeAttendanceEvents\EmployeeAttendanceEventResource;
use App\Filament\Resources\RecruitmentHistories\RecruitmentHistoryResource;
use App\Filament\Resources\WorkforceRosterHistories\WorkforceRosterHistoryResource;
use App\Models\RecruitmentApplication;
use App\Support\Filament\CompletedResourceSchema;
use App\Support\FilamentPermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('memoizes Filament resource and model permission metadata', function (): void {
    $registry = app(FilamentPermissionRegistry::class);

    $resources = $registry->resources();
    $parts = $registry->permissionPartsForModel(RecruitmentApplication::class);

    $reflection = new ReflectionClass(FilamentPermissionRegistry::class);
    $resourceCache = $reflection->getProperty('resourceClasses')->getValue();
    $modelCache = $reflection->getProperty('permissionPartsByModel')->getValue();

    expect($resources)->not->toBeEmpty()
        ->and($resourceCache)->toBe($resources)
        ->and($parts)->toBe([
            'module' => 'hr',
            'resource' => 'recruitment_application',
        ])
        ->and($modelCache)->toHaveKey(RecruitmentApplication::class);
});

it('memoizes completed resource schema column metadata after first lookup', function (): void {
    CompletedResourceSchema::tableColumnNames(RecruitmentApplication::class);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    CompletedResourceSchema::tableColumnNames(RecruitmentApplication::class);
    CompletedResourceSchema::tableColumnNames(RecruitmentApplication::class);

    expect($queries)->toBe(0);
});

it('runs the BIWMS performance audit as a report-only command', function (): void {
    expect(Artisan::call('biwms:performance-audit', [
        '--panel' => 'hr',
        '--details' => true,
    ]))->toBe(0);

    expect(Artisan::output())
        ->toContain('BIWMS Performance Audit')
        ->toContain('Mode: report-only')
        ->toContain('Resources scanned');
});

it('exports ranked performance audit findings without critical or high findings', function (): void {
    $exportPath = 'storage/framework/testing/performance-audit.json';
    @unlink(base_path($exportPath));

    expect(Artisan::call('biwms:performance-audit', [
        '--panel' => 'hr',
        '--export' => $exportPath,
    ]))->toBe(0);

    $report = json_decode((string) file_get_contents(base_path($exportPath)), true, 512, JSON_THROW_ON_ERROR);

    expect($report['summary']['critical'])->toBe(0)
        ->and($report['summary']['high'])->toBe(0)
        ->and($report['findings'])->not->toBeEmpty();
});

it('keeps high-volume history ledger and event resources out of global search', function (): void {
    foreach ([
        AttendanceLedgerEntryResource::class,
        EmployeeAttendanceEventResource::class,
        RecruitmentHistoryResource::class,
        WorkforceRosterHistoryResource::class,
    ] as $resourceClass) {
        $property = new ReflectionProperty($resourceClass, 'isGloballySearchable');

        expect($property->getValue())->toBeFalse();
    }
});

it('keeps the employee index table payload lean', function (): void {
    $source = (string) file_get_contents(app_path('Filament/Resources/Employees/Tables/EmployeesTable.php'));

    expect($source)->toContain('->defaultPaginationPageOption(10)')
        ->and($source)->toContain('->paginationPageOptions([10, 25, 50])')
        ->and($source)->not->toContain("Action::make('createLoginAccount')")
        ->and($source)->not->toContain("Action::make('downloadIdCards')")
        ->and($source)->toContain("Action::make('generateIdCard')")
        ->and($source)->toContain("Action::make('regenerateIdCard')");
});
