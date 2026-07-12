<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeConfirmationDecision;
use App\Models\PayrollLine;
use App\Models\PerformanceProbationReview;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentOnboardingTemplate;
use App\Models\RecruitmentOnboardingTemplateTask;
use App\Models\RecruitmentPreEmploymentCheck;
use App\Models\RecruitmentRequisition;
use App\Models\RecruitmentScreeningCriterion;
use App\Models\RecruitmentScreeningTemplate;
use App\Models\RecruitmentVacancy;
use App\Models\User;
use App\Services\Hr\EmployeeConfirmationDecisionService;
use App\Services\Hr\EmployeeOnboardingPlanService;
use App\Services\Hr\RecruitmentApplicationService;
use App\Services\Hr\RecruitmentHireService;
use App\Services\Hr\RecruitmentOfferService;
use App\Services\Hr\RecruitmentRequisitionService;
use App\Services\Hr\RecruitmentScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('enforces requisition approval and vacancy headcount and salary authority', function (): void {
    [$requester, $approver, $department, $manager] = recruitmentActors();

    expect(fn () => recruitmentRequisitionService()->createDraft([
        ...recruitmentRequisitionPayload($department, $manager),
        'requested_headcount' => 0,
    ], $requester->id))->toThrow(RuntimeException::class, 'headcount');

    expect(fn () => recruitmentRequisitionService()->createDraft([
        ...recruitmentRequisitionPayload($department, $manager),
        'budgeted_salary_min' => 2000,
        'budgeted_salary_max' => 1000,
    ], $requester->id))->toThrow(RuntimeException::class, 'salary maximum');

    $requisition = recruitmentRequisitionService()->createDraft(recruitmentRequisitionPayload($department, $manager), $requester->id);
    $submitted = recruitmentRequisitionService()->submit($requisition, $requester->id);

    expect(fn () => recruitmentRequisitionService()->approve($submitted, $requester->id))
        ->toThrow(RuntimeException::class, 'self-approval');

    $approved = recruitmentRequisitionService()->approve($submitted, $approver->id);

    expect(fn () => recruitmentRequisitionService()->createVacancy($approved, [
        ...recruitmentVacancyPayload(),
        'number_of_openings' => 3,
    ], $approver->id))->toThrow(RuntimeException::class, 'exceed');

    expect(fn () => recruitmentRequisitionService()->createVacancy($approved, [
        ...recruitmentVacancyPayload(),
        'salary_max' => 150000,
    ], $approver->id))->toThrow(RuntimeException::class, 'outside approved');

    $vacancy = recruitmentRequisitionService()->createVacancy($approved, recruitmentVacancyPayload(), $approver->id);

    expect($approved->fresh()->status)->toBe(RecruitmentRequisition::STATUS_OPEN)
        ->and($vacancy->number_of_openings)->toBe(1);
});

it('keeps candidate applications separate and records deterministic stage history', function (): void {
    [$requester, $approver, $department, $manager] = recruitmentActors();
    $vacancy = approvedVacancy($requester, $approver, $department, $manager);
    $candidate = RecruitmentCandidate::query()->create(recruitmentCandidatePayload());

    $application = app(RecruitmentApplicationService::class)->submit($candidate, $vacancy, [
        'application_number' => 'APP-1001',
        'cover_letter' => 'Interested in the role.',
    ], $candidate->candidate_user_id);

    expect(fn () => app(RecruitmentApplicationService::class)->submit($candidate, $vacancy, [
        'application_number' => 'APP-1002',
    ]))->toThrow(RuntimeException::class, 'already has');

    app(RecruitmentApplicationService::class)->moveToStage($application, 'screening', $approver->id, 'Ready for screening');
    app(RecruitmentApplicationService::class)->moveToStage($application->fresh(), 'shortlisted', $approver->id, 'Meets minimum criteria');

    expect($application->fresh()->current_stage)->toBe('shortlisted')
        ->and($application->histories()->pluck('to_stage')->all())->toBe(['applied', 'screening', 'shortlisted']);

    expect(fn () => app(RecruitmentApplicationService::class)->moveToStage($application->fresh(), 'hired', $approver->id))
        ->toThrow(RuntimeException::class, 'Invalid recruitment stage transition');
});

it('screens applications with criterion snapshots and never auto-rejects candidates', function (): void {
    [$requester, $approver, $department, $manager] = recruitmentActors();
    $application = activeApplication($requester, $approver, $department, $manager);
    app(RecruitmentApplicationService::class)->moveToStage($application, 'screening', $approver->id);

    $template = RecruitmentScreeningTemplate::query()->create([
        'code' => 'SCREEN-OPS',
        'name' => 'Operations Screening',
        'effective_from' => now()->toDateString(),
        'version' => 1,
        'is_active' => true,
    ]);
    RecruitmentScreeningCriterion::query()->create([
        'recruitment_screening_template_id' => $template->id,
        'code' => 'QUAL',
        'title' => 'Relevant qualification',
        'criterion_type' => 'qualification',
        'evaluation_type' => 'yes_no',
        'weight_percent' => 100,
        'is_mandatory' => true,
        'disqualifying_if_failed' => true,
    ]);

    $screening = app(RecruitmentScreeningService::class)->generate($application, $template, $approver->id);
    $completed = app(RecruitmentScreeningService::class)->complete($screening, [[
        'item_id' => $screening->items->first()->id,
        'passed' => false,
        'score' => 0,
        'comment' => 'Qualification not evidenced yet.',
    ]], $approver->id);

    expect($completed->items()->count())->toBe(1)
        ->and($completed->mandatory_criteria_passed)->toBeFalse()
        ->and($completed->recommendation)->toBe('manual_review')
        ->and($application->fresh()->current_stage)->toBe('screening');

    expect(fn () => app(RecruitmentScreeningService::class)->override($completed, 'proceed', ''))
        ->toThrow(RuntimeException::class, 'reason');
});

it('accepts offers without creating payroll lines and converts explicitly through employee onboarding', function (): void {
    [$requester, $approver, $department, $manager] = recruitmentActors();
    $application = activeApplication($requester, $approver, $department, $manager, 'APP-HIRE');
    app(RecruitmentApplicationService::class)->moveToStage($application, 'screening', $approver->id);
    app(RecruitmentApplicationService::class)->moveToStage($application->fresh(), 'shortlisted', $approver->id);
    app(RecruitmentApplicationService::class)->moveToStage($application->fresh(), 'interview', $approver->id);
    app(RecruitmentApplicationService::class)->moveToStage($application->fresh(), 'selection_review', $approver->id);
    app(RecruitmentApplicationService::class)->moveToStage($application->fresh(), 'offer', $approver->id);

    $offer = app(RecruitmentOfferService::class)->draft($application->fresh(), [
        'offer_number' => 'OFF-1001',
        'proposed_start_date' => now()->addWeek()->toDateString(),
        'probation_months' => 3,
        'base_salary' => 75000,
        'currency' => 'USD',
        'pay_frequency' => 'monthly',
        'valid_until' => now()->addMonth()->toDateString(),
        'reporting_manager_employee_id' => $manager->id,
    ]);
    app(RecruitmentOfferService::class)->approve($offer, $approver->id);
    $accepted = app(RecruitmentOfferService::class)->accept(app(RecruitmentOfferService::class)->issue($offer->fresh(), $approver->id));

    expect($accepted->status)->toBe(RecruitmentOffer::STATUS_ACCEPTED)
        ->and(PayrollLine::query()->count())->toBe(0);

    expect(fn () => $accepted->update(['base_salary' => 1]))->toThrow(RuntimeException::class, 'immutable');

    RecruitmentPreEmploymentCheck::query()->create([
        'recruitment_application_id' => $application->id,
        'check_type' => 'identity',
        'status' => 'in_progress',
    ]);

    expect(fn () => app(RecruitmentHireService::class)->convert($application->fresh(), $approver->id))
        ->toThrow(RuntimeException::class, 'checks');

    RecruitmentPreEmploymentCheck::query()->update(['status' => 'cleared']);
    $template = recruitmentOnboardingTemplate();
    $employee = app(RecruitmentHireService::class)->convert($application->fresh(), $approver->id, $template);
    $retry = app(RecruitmentHireService::class)->convert($application->fresh(), $approver->id, $template);

    expect($employee->id)->toBe($retry->id)
        ->and($application->fresh()->hired_employee_id)->toBe($employee->id)
        ->and($application->fresh()->vacancy->filled_openings)->toBe(1)
        ->and(PerformanceProbationReview::query()->where('employee_id', $employee->id)->exists())->toBeTrue()
        ->and(PayrollLine::query()->count())->toBe(0);
});

it('generates onboarding tasks and prevents silent mandatory completion gaps', function (): void {
    [, , $department] = recruitmentActors();
    $employee = Employee::factory()->create(['department_id' => $department->id]);
    $template = recruitmentOnboardingTemplate();

    $plan = app(EmployeeOnboardingPlanService::class)->generate($employee, $template, now());
    $task = $plan->tasks()->firstOrFail();
    app(EmployeeOnboardingPlanService::class)->completeTask($task, User::factory()->create()->id);
    app(EmployeeOnboardingPlanService::class)->approveTask($task->fresh(), User::factory()->create()->id);

    expect($plan->fresh()->status)->toBe('completed')
        ->and((float) $plan->fresh()->progress_percent)->toBe(100.0);
});

it('keeps confirmation recommendation and implementation separate', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();
    $review = PerformanceProbationReview::query()->create([
        'employee_id' => $employee->id,
        'probation_start_date' => now()->subMonths(3)->toDateString(),
        'expected_confirmation_date' => now()->toDateString(),
        'review_date' => now()->toDateString(),
        'review_type' => 'final',
        'status' => 'completed',
        'manager_recommendation' => 'confirm',
    ]);
    $decision = EmployeeConfirmationDecision::query()->create([
        'employee_id' => $employee->id,
        'performance_probation_review_id' => $review->id,
        'decision_type' => 'confirm',
        'proposed_effective_date' => now()->toDateString(),
        'reason' => 'Probation objectives achieved.',
        'status' => 'draft',
    ]);

    app(EmployeeConfirmationDecisionService::class)->submit($decision, $user->id);
    app(EmployeeConfirmationDecisionService::class)->approve($decision->fresh(), $user->id);
    $implemented = app(EmployeeConfirmationDecisionService::class)->implement($decision->fresh(), $user->id);

    expect($implemented->status)->toBe('implemented')
        ->and($implemented->implemented_at)->not->toBeNull();

    expect(fn () => app(EmployeeConfirmationDecisionService::class)->implement($implemented->fresh(), $user->id))
        ->toThrow(RuntimeException::class, 'approved');
});

it('exports recruitment reconcile findings without mutating data', function (): void {
    [$requester, $approver, $department, $manager] = recruitmentActors();
    $vacancy = approvedVacancy($requester, $approver, $department, $manager);
    $application = activeApplication($requester, $approver, $department, $manager, 'APP-RECON');
    $application->updateQuietly(['current_stage' => 'interview']);
    $vacancy->updateQuietly(['status' => 'closed']);
    $path = 'storage/app/reports/recruitment-reconcile-test.json';
    @unlink(base_path($path));

    Artisan::call('biwms:recruitment-reconcile', [
        '--details' => true,
        '--export' => $path,
    ]);

    expect(file_exists(base_path($path)))->toBeTrue();
    $payload = json_decode((string) file_get_contents(base_path($path)), true);

    expect($payload['finding_count'])->toBeGreaterThan(0)
        ->and(collect($payload['findings'])->pluck('classification')->contains('application_stage_history_mismatch'))->toBeTrue();
});

function recruitmentActors(): array
{
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $manager = Employee::factory()->create(['employee_number' => 'MGR-'.str()->upper(str()->random(5))]);
    $department = Department::query()->create([
        'department_code' => 'REC-'.str()->upper(str()->random(4)),
        'name' => 'Recruitment Test Department',
        'type' => 'human_resources',
        'status' => 'active',
        'manager_id' => $manager->id,
    ]);

    return [$requester, $approver, $department, $manager];
}

function recruitmentRequisitionService(): RecruitmentRequisitionService
{
    return app(RecruitmentRequisitionService::class);
}

function recruitmentRequisitionPayload(Department $department, Employee $manager): array
{
    return [
        'requisition_number' => 'REQ-'.str()->upper(str()->random(8)),
        'title' => 'Operations Supervisor',
        'department_id' => $department->id,
        'employment_type' => 'permanent',
        'requested_headcount' => 1,
        'requisition_type' => 'new_position',
        'budgeted_salary_min' => 50000,
        'budgeted_salary_max' => 100000,
        'currency' => 'USD',
        'justification' => 'Approved growth in operations.',
        'hiring_manager_employee_id' => $manager->id,
        'priority' => 'normal',
    ];
}

function recruitmentVacancyPayload(): array
{
    return [
        'vacancy_number' => 'VAC-'.str()->upper(str()->random(8)),
        'number_of_openings' => 1,
        'opening_date' => now()->toDateString(),
        'status' => 'open',
        'visibility' => 'internal_and_external',
        'description' => 'Coordinate operations team activities.',
        'salary_min' => 60000,
        'salary_max' => 90000,
        'currency' => 'USD',
    ];
}

function recruitmentCandidatePayload(): array
{
    return [
        'candidate_number' => 'CAND-'.str()->upper(str()->random(8)),
        'first_name' => 'Ada',
        'last_name' => 'Cole',
        'email' => 'candidate.'.str()->lower(str()->random(6)).'@example.test',
        'phone' => '+15550001',
        'source' => 'direct',
        'consent_given_at' => now(),
        'status' => 'active',
    ];
}

function approvedVacancy(User $requester, User $approver, Department $department, Employee $manager): RecruitmentVacancy
{
    $requisition = recruitmentRequisitionService()->createDraft(recruitmentRequisitionPayload($department, $manager), $requester->id);
    $submitted = recruitmentRequisitionService()->submit($requisition, $requester->id);
    $approved = recruitmentRequisitionService()->approve($submitted, $approver->id);

    return recruitmentRequisitionService()->createVacancy($approved, recruitmentVacancyPayload(), $approver->id);
}

function activeApplication(User $requester, User $approver, Department $department, Employee $manager, string $applicationNumber = 'APP-BASE'): RecruitmentApplication
{
    $vacancy = approvedVacancy($requester, $approver, $department, $manager);
    $candidate = RecruitmentCandidate::query()->create(recruitmentCandidatePayload());

    return app(RecruitmentApplicationService::class)->submit($candidate, $vacancy, [
        'application_number' => $applicationNumber.'-'.str()->upper(str()->random(5)),
    ], $candidate->candidate_user_id);
}

function recruitmentOnboardingTemplate(): RecruitmentOnboardingTemplate
{
    $template = RecruitmentOnboardingTemplate::query()->create([
        'code' => 'ONB-'.str()->upper(str()->random(5)),
        'name' => 'Standard Onboarding',
        'is_active' => true,
        'version' => 1,
    ]);

    RecruitmentOnboardingTemplateTask::query()->create([
        'recruitment_onboarding_template_id' => $template->id,
        'code' => 'DOCS',
        'title' => 'Submit employment forms',
        'task_category' => 'documentation',
        'responsible_role_type' => 'employee',
        'due_offset_days' => 1,
        'is_required' => true,
        'requires_approval' => true,
    ]);

    return $template;
}
