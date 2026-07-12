<?php

declare(strict_types=1);

use App\Filament\Resources\EmployeeConfirmationDecisions\EmployeeConfirmationDecisionResource;
use App\Filament\Resources\PerformanceAppraisalHistories\PerformanceAppraisalHistoryResource;
use App\Filament\Resources\RecruitmentHistories\RecruitmentHistoryResource;
use App\Filament\Resources\RecruitmentRequisitions\RecruitmentRequisitionResource;
use App\Filament\Resources\WorkforceRosterHistories\WorkforceRosterHistoryResource;
use Illuminate\Support\Facades\File;

function hrResourceFiles(): array
{
    $patterns = [
        app_path('Filament/Resources/*Attendance*'),
        app_path('Filament/Resources/*Leave*'),
        app_path('Filament/Resources/*Workforce*'),
        app_path('Filament/Resources/*Performance*'),
        app_path('Filament/Resources/*Recruitment*'),
        app_path('Filament/Resources/EmployeeConfirmationDecisions'),
        app_path('Filament/Resources/EmployeePayslip*'),
        app_path('Filament/Resources/Payroll*'),
        app_path('Filament/Resources/PayCodes'),
        app_path('Filament/Resources/Employees'),
        app_path('Filament/Resources/EmployeeIdCard*'),
    ];

    return collect($patterns)
        ->flatMap(fn (string $pattern): array => glob($pattern, GLOB_ONLYDIR) ?: [])
        ->flatMap(fn (string $directory): array => File::allFiles($directory))
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->unique()
        ->values()
        ->all();
}

it('has no empty generated HR resource schemas or tables', function (): void {
    foreach (hrResourceFiles() as $file) {
        $contents = file_get_contents($file);

        expect($contents)->not->toMatch('/->components\(\[\s*\/\/\s*\]\)/s', $file.' has an empty component schema.')
            ->and($contents)->not->toMatch('/->columns\(\[\s*\/\/\s*\]\)/s', $file.' has an empty table schema.')
            ->and($contents)->not->toMatch('/->schema\(\[\s*\/\/\s*\]\)/s', $file.' has an empty relation schema.');
    }
});

it('keeps HR resource metadata present for generated recruitment and confirmation resources', function (): void {
    foreach ([RecruitmentRequisitionResource::class, EmployeeConfirmationDecisionResource::class] as $resourceClass) {
        expect($resourceClass::permissionModule())->toBe('hr')
            ->and($resourceClass::permissionResource())->not->toBe('');
    }
});

it('keeps append-only HR history resources read-only', function (string $resourceClass): void {
    $pages = $resourceClass::getPages();

    expect($pages)->toHaveKey('index')
        ->and($pages)->toHaveKey('view')
        ->and($pages)->not->toHaveKey('create')
        ->and($pages)->not->toHaveKey('edit')
        ->and($resourceClass::canCreate())->toBeFalse();
})->with([
    RecruitmentHistoryResource::class,
    PerformanceAppraisalHistoryResource::class,
    WorkforceRosterHistoryResource::class,
]);

it('registers the completed HR modules in the HR panel provider', function (): void {
    $provider = file_get_contents(app_path('Providers/Filament/HrPanelProvider.php'));

    foreach ([
        'RecruitmentRequisitionResource::class',
        'RecruitmentDashboard::class',
        'PerformanceAppraisalResource::class',
        'WorkforceRosterPeriodResource::class',
        'AttendanceReviewPeriodResource::class',
    ] as $needle) {
        expect($provider)->toContain($needle);
    }
});
