<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps recruitment and attendance review resources off the generic completed schema fallback', function (): void {
    $resourceFiles = collect(glob(app_path('Filament/Resources/Recruitment*/*/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/Recruitment*/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/EmployeeConfirmationDecisions/*/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/EmployeeConfirmationDecisions/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/AttendanceReviewPeriods/Schemas/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/AttendanceReviewItems/Schemas/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/AttendancePayrollReviewBatches/Schemas/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/AttendancePayrollReviewBatchLines/Schemas/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/AttendancePayrollRules/Schemas/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/EmployeeAttendanceDays/Schemas/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/EmployeeAttendanceEvents/Schemas/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/AttendanceCorrectionRequests/Schemas/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/AttendanceDevices/Schemas/*.php')) ?: [])
        ->merge(glob(app_path('Filament/Resources/AttendanceLocations/Schemas/*.php')) ?: []);

    expect($resourceFiles)->not->toBeEmpty();

    foreach ($resourceFiles as $resourceFile) {
        expect((string) file_get_contents($resourceFile))
            ->not->toContain('CompletedResourceSchema::');
    }
});

it('uses installed heroicons in recruitment and attendance review schema helpers', function (): void {
    foreach ([
        app_path('Support/Filament/RecruitmentResourceSchema.php'),
        app_path('Support/Filament/AttendanceReviewResourceSchema.php'),
    ] as $schemaPath) {
        $source = (string) file_get_contents($schemaPath);

        preg_match_all('/heroicon-([omsc]-[a-z0-9-]+)/', $source, $matches);

        foreach (array_unique($matches[1]) as $iconName) {
            expect(base_path("vendor/blade-ui-kit/blade-heroicons/resources/svg/{$iconName}.svg"))
                ->toBeFile();
        }
    }
});

it('renders representative recruitment and attendance review index pages for a confirmed super admin', function (): void {
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $superAdmin->assignRole('super_admin');

    foreach ([
        '/admin/recruitment-dashboard',
        '/admin/recruitment-reports',
        '/admin/recruitment-requisitions',
        '/admin/recruitment-vacancies',
        '/admin/recruitment-candidates',
        '/admin/recruitment-applications',
        '/admin/recruitment-offers',
        '/admin/recruitment-onboarding-plans',
        '/admin/employee-confirmation-decisions',
        '/admin/attendance-review-periods',
        '/admin/attendance-review-items',
        '/admin/attendance-payroll-review-batches',
        '/admin/attendance-payroll-review-batch-lines',
        '/admin/attendance-payroll-rules',
        '/admin/employee-attendance-days',
        '/admin/employee-attendance-events',
        '/admin/attendance-correction-requests',
        '/admin/attendance-devices',
        '/admin/attendance-locations',
    ] as $path) {
        $this
            ->actingAs($superAdmin)
            ->withSession(['two_factor_passed_at' => now()->timestamp])
            ->get($path)
            ->assertSuccessful();
    }
});

it('renders supported recruitment and attendance setup create pages for a confirmed super admin', function (): void {
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $superAdmin->assignRole('super_admin');

    foreach ([
        '/admin/recruitment-requisitions/create',
        '/admin/recruitment-vacancies/create',
        '/admin/recruitment-job-postings/create',
        '/admin/recruitment-candidates/create',
        '/admin/recruitment-applications/create',
        '/admin/recruitment-application-screenings/create',
        '/admin/recruitment-assessments/create',
        '/admin/recruitment-interviews/create',
        '/admin/recruitment-interview-panels/create',
        '/admin/recruitment-interview-scorecard-templates/create',
        '/admin/recruitment-selection-reviews/create',
        '/admin/recruitment-offers/create',
        '/admin/recruitment-pre-employment-checks/create',
        '/admin/recruitment-onboarding-templates/create',
        '/admin/recruitment-onboarding-plans/create',
        '/admin/recruitment-onboarding-tasks/create',
        '/admin/recruitment-screening-templates/create',
        '/admin/employee-confirmation-decisions/create',
        '/admin/attendance-review-periods/create',
        '/admin/attendance-review-items/create',
        '/admin/attendance-payroll-review-batches/create',
        '/admin/attendance-payroll-review-batch-lines/create',
        '/admin/attendance-payroll-rules/create',
        '/admin/attendance-correction-requests/create',
        '/admin/attendance-devices/create',
        '/admin/attendance-locations/create',
    ] as $path) {
        $this
            ->actingAs($superAdmin)
            ->withSession(['two_factor_passed_at' => now()->timestamp])
            ->get($path)
            ->assertSuccessful();
    }
});

it('renders useful recruitment dashboard and report content', function (): void {
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $superAdmin = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $superAdmin->assignRole('super_admin');

    $this
        ->actingAs($superAdmin)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/recruitment-dashboard')
        ->assertSuccessful()
        ->assertSee('Open Requisitions')
        ->assertSee('Recent Applications')
        ->assertSee('Recent Offers');

    $this
        ->actingAs($superAdmin)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/recruitment-reports')
        ->assertSuccessful()
        ->assertSee('Applications by Stage')
        ->assertSee('Vacancies by Status')
        ->assertSee('Offers by Status');
});
