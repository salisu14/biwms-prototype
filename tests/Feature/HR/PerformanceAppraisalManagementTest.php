<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PerformanceAppraisal;
use App\Models\PerformanceAppraisalCycle;
use App\Models\PerformanceAppraisalModerationItem;
use App\Models\PerformanceAppraisalModerationSession;
use App\Models\PerformanceAppraisalTemplate;
use App\Models\PerformanceAppraisalTemplateItem;
use App\Models\PerformanceAppraisalTemplateSection;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalPlan;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceProbationReview;
use App\Models\PerformanceRatingScale;
use App\Models\PerformanceRatingScaleLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Hr\PerformanceAppraisalCycleService;
use App\Services\Hr\PerformanceAppraisalFinalizationService;
use App\Services\Hr\PerformanceAppraisalModerationService;
use App\Services\Hr\PerformanceGoalService;
use App\Services\Hr\PerformanceManagerAssessmentService;
use App\Services\Hr\PerformanceSelfAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('validates rating scale level ranges and default scale overlap', function (): void {
    $scale = performanceScale();

    PerformanceRatingScaleLevel::query()->create([
        'performance_rating_scale_id' => $scale->id,
        'code' => 'MEETS',
        'name' => 'Meets Expectations',
        'score_from' => 60,
        'score_to' => 79.99,
        'sort_order' => 1,
    ]);

    expect(fn () => PerformanceRatingScaleLevel::query()->create([
        'performance_rating_scale_id' => $scale->id,
        'code' => 'OVERLAP',
        'name' => 'Overlap',
        'score_from' => 70,
        'score_to' => 85,
        'sort_order' => 2,
    ]))->toThrow(RuntimeException::class, 'must not overlap');

    expect(fn () => PerformanceRatingScale::query()->create([
        'code' => 'DUP',
        'name' => 'Duplicate Default',
        'minimum_score' => 0,
        'maximum_score' => 100,
        'is_default' => true,
        'is_active' => true,
        'effective_from' => '2026-01-01',
    ]))->toThrow(RuntimeException::class, 'Only one active default');
});

it('rejects invalid cycle and template weights', function (): void {
    $scale = performanceScale(isDefault: false, code: 'SCALE2');

    expect(fn () => performanceCycle($scale, from: '2026-12-31', to: '2026-01-01'))
        ->toThrow(RuntimeException::class, 'start date');

    expect(fn () => PerformanceAppraisalTemplate::query()->create([
        'code' => 'BAD',
        'name' => 'Bad Template',
        'rating_scale_id' => $scale->id,
        'goal_weight_percent' => 40,
        'competency_weight_percent' => 40,
        'other_weight_percent' => 10,
        'effective_from' => '2026-01-01',
    ]))->toThrow(RuntimeException::class, 'weights must total 100%');
});

it('generates deterministic cycle assignments and appraisals with snapshots', function (): void {
    $user = User::factory()->create();
    [$employee, $manager] = performanceEmployeeWithManager();
    $scale = performanceScale(isDefault: false, code: 'SCALE3');
    performanceScaleLevel($scale, 'MEETS', 0, 100);
    $template = performanceTemplate($scale);
    $cycle = performanceCycle($scale);

    $service = app(PerformanceAppraisalCycleService::class);
    $service->generateAssignments($cycle, $template, $user->id);
    $service->generateAssignments($cycle->fresh(), $template, $user->id);
    $service->generateAppraisals($cycle->fresh(), $user->id);
    $service->generateAppraisals($cycle->fresh(), $user->id);

    $assignment = $cycle->assignments()->where('employee_id', $employee->id)->first();
    $appraisal = PerformanceAppraisal::query()->first();

    expect($cycle->assignments()->count())->toBe(2)
        ->and($assignment->manager_employee_id)->toBe($manager->id)
        ->and(PerformanceAppraisal::query()->count())->toBe(2)
        ->and($appraisal->template_snapshot)->toBeArray()
        ->and($appraisal->rating_scale_snapshot)->toBeArray()
        ->and($appraisal->sections()->count())->toBe(1)
        ->and($appraisal->items()->count())->toBe(2);
});

it('preserves self assessment, manager assessment, scoring, finalization, and acknowledgement separately', function (): void {
    $user = User::factory()->create();
    [$employee, $manager] = performanceEmployeeWithManager();
    $employeeUser = User::factory()->create(['employee_id' => $employee->id]);
    $managerUser = User::factory()->create(['employee_id' => $manager->id]);
    $scale = performanceScale(isDefault: false, code: 'SCALE4');
    $level = performanceScaleLevel($scale, 'EXCEEDS', 80, 100);
    $template = performanceTemplate($scale);
    $cycle = performanceCycle($scale, code: 'CY-2026-B');

    app(PerformanceAppraisalCycleService::class)->generateAssignments($cycle, $template, $user->id);
    app(PerformanceAppraisalCycleService::class)->generateAppraisals($cycle->fresh(), $user->id);
    $appraisal = PerformanceAppraisal::query()->where('employee_id', $employee->id)->firstOrFail();
    $items = $appraisal->items()->get();

    app(PerformanceSelfAssessmentService::class)->submit($appraisal, $employee->id, $employeeUser->id, [
        ['item_id' => $items[0]->id, 'rating' => 90, 'comment' => 'I delivered the target.'],
        ['item_id' => $items[1]->id, 'rating' => 80, 'comment' => 'I improved collaboration.'],
    ]);

    $managerSubmitted = app(PerformanceManagerAssessmentService::class)->submit($appraisal->fresh(), $manager->id, $managerUser->id, [
        ['item_id' => $items[0]->id, 'rating' => 92, 'comment' => 'Strong KPI delivery.'],
        ['item_id' => $items[1]->id, 'rating' => 88, 'comment' => 'Reliable teamwork.'],
    ]);

    $finalized = app(PerformanceAppraisalFinalizationService::class)->finalize($managerSubmitted, $user->id, 'Finalized after manager review.');
    $acknowledgement = app(PerformanceAppraisalFinalizationService::class)->acknowledge($finalized, $employee->id, $employeeUser->id, 'acknowledged_with_comment', 'Acknowledged, with thanks.');

    expect((float) $finalized->final_score)->toBe(90.0)
        ->and($finalized->final_rating_level_id)->toBe($level->id)
        ->and($acknowledgement->employee_comment)->toBe('Acknowledged, with thanks.')
        ->and(PayrollLine::query()->count())->toBe(0);

    expect(fn () => app(PerformanceSelfAssessmentService::class)->submit($finalized->fresh(), $employee->id, $employeeUser->id, []))
        ->toThrow(RuntimeException::class, 'locked');
});

it('requires goal weights to total 100 and preserves update history', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['employee_number' => 'PERF-GOAL']);
    $scale = performanceScale(isDefault: false, code: 'SCALE5');
    $cycle = performanceCycle($scale, code: 'CY-2026-C');
    $plan = PerformanceGoalPlan::query()->create([
        'performance_appraisal_cycle_id' => $cycle->id,
        'employee_id' => $employee->id,
        'status' => PerformanceGoalPlan::STATUS_DRAFT,
    ]);
    $goal = performanceGoal($plan, $employee, 60);

    expect(fn () => app(PerformanceGoalService::class)->approvePlan($plan, $user->id))
        ->toThrow(RuntimeException::class, 'weights must total 100%');

    performanceGoal($plan, $employee, 40, 'Second goal');
    $approved = app(PerformanceGoalService::class)->approvePlan($plan->fresh(), $user->id);
    app(PerformanceGoalService::class)->submitUpdate($goal->fresh(), $user->id, 25, 25, 'Quarter progress');
    app(PerformanceGoalService::class)->submitUpdate($goal->fresh(), $user->id, 50, 50, 'Half progress');

    $goal->refresh()->update(['target_value' => 120]);

    expect($approved->status)->toBe(PerformanceGoalPlan::STATUS_APPROVED)
        ->and($goal->updates()->count())->toBe(2)
        ->and($goal->fresh()->status)->toBe(PerformanceGoal::STATUS_PROPOSED);
});

it('protects own, manager, and cross-team performance access', function (): void {
    [$employee, $manager] = performanceEmployeeWithManager('PERF-AUTH');
    $other = Employee::factory()->create(['employee_number' => 'PERF-OTHER']);
    $employeeUser = User::factory()->create(['employee_id' => $employee->id]);
    $managerUser = User::factory()->create(['employee_id' => $manager->id]);
    $otherUser = User::factory()->create(['employee_id' => $other->id]);
    performanceGivePermissions($employeeUser, ['hr.my_performance.view', 'hr.my_self_assessment.submit']);
    performanceGivePermissions($managerUser, ['hr.team_performance.view', 'hr.team_performance.assess']);
    $scale = performanceScale(isDefault: false, code: 'SCALE-AUTH');
    $template = performanceTemplate($scale);
    $cycle = performanceCycle($scale, code: 'CY-AUTH');
    app(PerformanceAppraisalCycleService::class)->generateAssignments($cycle, $template, User::factory()->create()->id);
    app(PerformanceAppraisalCycleService::class)->generateAppraisals($cycle->fresh(), User::factory()->create()->id);
    $appraisal = PerformanceAppraisal::query()->where('employee_id', $employee->id)->firstOrFail();

    expect(Gate::forUser($employeeUser)->allows('view', $appraisal))->toBeTrue()
        ->and(Gate::forUser($employeeUser)->allows('selfAssess', $appraisal))->toBeTrue()
        ->and(Gate::forUser($managerUser)->allows('managerAssess', $appraisal))->toBeTrue()
        ->and(Gate::forUser($otherUser)->allows('view', $appraisal))->toBeFalse();
});

it('requires moderation reasons and keeps PIP and probation decisions advisory', function (): void {
    $user = User::factory()->create();
    [$employee] = performanceEmployeeWithManager('PERF-PIP');
    $scale = performanceScale(isDefault: false, code: 'SCALE6');
    performanceScaleLevel($scale, 'ANY', 0, 100);
    $template = performanceTemplate($scale);
    $cycle = performanceCycle($scale, code: 'CY-2026-D');
    app(PerformanceAppraisalCycleService::class)->generateAssignments($cycle, $template, $user->id);
    app(PerformanceAppraisalCycleService::class)->generateAppraisals($cycle->fresh(), $user->id);
    $appraisal = PerformanceAppraisal::query()->where('employee_id', $employee->id)->firstOrFail();
    $session = PerformanceAppraisalModerationSession::query()->create([
        'performance_appraisal_cycle_id' => $cycle->id,
        'code' => 'MOD-1',
        'name' => 'Moderation',
        'created_by' => $user->id,
    ]);
    $moderationItem = PerformanceAppraisalModerationItem::query()->create([
        'performance_appraisal_moderation_session_id' => $session->id,
        'performance_appraisal_id' => $appraisal->id,
        'original_score' => 70,
        'status' => 'pending',
    ]);

    expect(fn () => app(PerformanceAppraisalModerationService::class)->adjust($moderationItem, 80, '', $user->id))
        ->toThrow(RuntimeException::class, 'reason is required');

    PerformanceImprovementPlan::query()->create([
        'employee_id' => $employee->id,
        'initiated_by' => $user->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-09-30',
        'status' => PerformanceImprovementPlan::STATUS_UNSUCCESSFULLY_COMPLETED,
        'reason_summary' => 'Support required',
        'expectations_summary' => 'Improve output',
    ]);
    PerformanceProbationReview::query()->create([
        'employee_id' => $employee->id,
        'probation_start_date' => '2026-01-01',
        'expected_confirmation_date' => '2026-06-30',
        'review_date' => '2026-06-15',
        'manager_recommendation' => 'confirm',
        'status' => 'completed',
    ]);

    expect($employee->fresh()->is_active)->toBeTrue()
        ->and(PayrollLine::query()->count())->toBe(0);
});

it('exports report-only performance reconcile diagnostics', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['employee_number' => 'PERF-REC']);
    $scale = performanceScale(isDefault: false, code: 'SCALE7');
    $cycle = performanceCycle($scale, code: 'CY-2026-E');
    $template = performanceTemplate($scale);

    $cycle->assignments()->create([
        'employee_id' => $employee->id,
        'appraisal_template_id' => $template->id,
        'rating_scale_id' => $scale->id,
        'eligibility_status' => 'eligible',
        'assigned_by' => $user->id,
        'assigned_at' => now(),
    ]);

    $exportPath = 'storage/app/testing/performance-reconcile.json';

    $this->artisan('biwms:performance-reconcile', ['--details' => true, '--export' => $exportPath])
        ->expectsOutputToContain('BIWMS Performance Reconcile')
        ->assertSuccessful();

    $report = json_decode((string) file_get_contents(base_path($exportPath)), true);

    expect($report['findings'])->toBeArray()
        ->and(collect($report['findings'])->pluck('classification'))->toContain('eligible_employee_missing_appraisal');
});

function performanceScale(bool $isDefault = true, string $code = 'PERF-100'): PerformanceRatingScale
{
    return PerformanceRatingScale::query()->create([
        'code' => $code,
        'name' => $code,
        'minimum_score' => 0,
        'maximum_score' => 100,
        'is_default' => $isDefault,
        'is_active' => true,
        'effective_from' => '2026-01-01',
    ]);
}

function performanceScaleLevel(PerformanceRatingScale $scale, string $code, float $from, float $to): PerformanceRatingScaleLevel
{
    return PerformanceRatingScaleLevel::query()->create([
        'performance_rating_scale_id' => $scale->id,
        'code' => $code,
        'name' => $code,
        'score_from' => $from,
        'score_to' => $to,
        'sort_order' => 1,
    ]);
}

function performanceCycle(PerformanceRatingScale $scale, string $from = '2026-01-01', string $to = '2026-12-31', string $code = 'CY-2026-A')
{
    return PerformanceAppraisalCycle::query()->create([
        'code' => $code,
        'name' => $code,
        'cycle_type' => 'annual',
        'period_start' => $from,
        'period_end' => $to,
        'rating_scale_id' => $scale->id,
        'status' => 'draft',
    ]);
}

function performanceTemplate(PerformanceRatingScale $scale): PerformanceAppraisalTemplate
{
    $template = PerformanceAppraisalTemplate::query()->create([
        'code' => 'TPL-'.fake()->unique()->numerify('###'),
        'name' => 'Performance Template',
        'rating_scale_id' => $scale->id,
        'goal_weight_percent' => 60,
        'competency_weight_percent' => 30,
        'other_weight_percent' => 10,
        'effective_from' => '2026-01-01',
        'version' => 1,
        'is_active' => true,
    ]);

    $section = PerformanceAppraisalTemplateSection::query()->create([
        'performance_appraisal_template_id' => $template->id,
        'code' => 'GOALS',
        'title' => 'Goals',
        'section_type' => 'goals',
        'weight_percent' => 100,
        'sort_order' => 1,
        'allow_employee_rating' => true,
        'allow_manager_rating' => true,
    ]);

    PerformanceAppraisalTemplateItem::query()->create([
        'performance_appraisal_template_section_id' => $section->id,
        'code' => 'KPI',
        'title' => 'KPI delivery',
        'measurement_type' => 'rating',
        'weight_percent' => 50,
        'scoring_direction' => 'manual',
    ]);

    PerformanceAppraisalTemplateItem::query()->create([
        'performance_appraisal_template_section_id' => $section->id,
        'code' => 'TEAM',
        'title' => 'Team contribution',
        'measurement_type' => 'rating',
        'weight_percent' => 50,
        'scoring_direction' => 'manual',
    ]);

    return $template;
}

function performanceEmployeeWithManager(string $prefix = 'PERF'): array
{
    $manager = Employee::factory()->create(['employee_number' => $prefix.'-MGR']);
    $department = Department::query()->create([
        'department_code' => $prefix.'-DPT',
        'name' => $prefix.' Department',
        'manager_id' => $manager->id,
    ]);
    $employee = Employee::factory()->create([
        'employee_number' => $prefix.'-EMP',
        'department_id' => $department->id,
    ]);

    return [$employee, $manager, $department];
}

function performanceGoal(PerformanceGoalPlan $plan, Employee $employee, float $weight, string $title = 'Goal'): PerformanceGoal
{
    return PerformanceGoal::query()->create([
        'performance_goal_plan_id' => $plan->id,
        'employee_id' => $employee->id,
        'title' => $title,
        'description' => $title,
        'measurement_type' => 'numeric',
        'target_value' => 100,
        'weight_percent' => $weight,
        'start_date' => '2026-01-01',
        'due_date' => '2026-12-31',
        'status' => PerformanceGoal::STATUS_DRAFT,
    ]);
}

function performanceGivePermissions(User $user, array $permissions): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::query()->create([
        'name' => 'performance-test-'.fake()->unique()->numerify('####'),
        'guard_name' => 'web',
    ]);
    $role->syncPermissions($permissions);
    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}
