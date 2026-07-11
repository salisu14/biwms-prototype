<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_rating_scales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('minimum_score', 10, 4);
            $table->decimal('maximum_score', 10, 4);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'is_default', 'is_active'], 'perf_scale_default_idx');
        });

        Schema::create('performance_rating_scale_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_rating_scale_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->decimal('score_from', 10, 4);
            $table->decimal('score_to', 10, 4);
            $table->decimal('numeric_value', 10, 4)->nullable();
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_passing')->nullable();
            $table->timestamps();

            $table->unique(['performance_rating_scale_id', 'code'], 'perf_scale_level_code_unique');
            $table->index(['performance_rating_scale_id', 'score_from', 'score_to'], 'perf_scale_level_range_idx');
        });

        Schema::create('performance_appraisal_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('cycle_type', 30)->default('annual')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('goal_setting_start')->nullable();
            $table->date('goal_setting_end')->nullable();
            $table->date('self_assessment_start')->nullable();
            $table->date('self_assessment_end')->nullable();
            $table->date('manager_review_start')->nullable();
            $table->date('manager_review_end')->nullable();
            $table->date('moderation_start')->nullable();
            $table->date('moderation_end')->nullable();
            $table->date('acknowledgement_deadline')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('rating_scale_id')->constrained('performance_rating_scales')->restrictOnDelete();
            $table->boolean('allow_self_assessment')->default(true);
            $table->boolean('allow_peer_review')->default(false);
            $table->boolean('allow_secondary_reviewer')->default(false);
            $table->boolean('require_employee_acknowledgement')->default(true);
            $table->boolean('require_moderation')->default(false);
            $table->boolean('lock_completed_reviews')->default(true);
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->index(['period_start', 'period_end', 'status'], 'perf_cycle_period_status_idx');
        });

        Schema::create('performance_appraisal_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('applicable_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->unsignedBigInteger('applicable_position_id')->nullable();
            $table->unsignedBigInteger('applicable_grade_id')->nullable();
            $table->string('applicable_employment_type')->nullable();
            $table->foreignId('rating_scale_id')->constrained('performance_rating_scales')->restrictOnDelete();
            $table->decimal('goal_weight_percent', 8, 4)->default(50);
            $table->decimal('competency_weight_percent', 8, 4)->default(40);
            $table->decimal('other_weight_percent', 8, 4)->default(10);
            $table->boolean('require_self_comment')->default(false);
            $table->boolean('require_manager_comment')->default(false);
            $table->boolean('require_final_comment')->default(false);
            $table->boolean('allow_not_applicable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['business_id', 'code', 'version'], 'perf_template_version_unique');
        });

        Schema::create('performance_appraisal_cycle_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('secondary_reviewer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('appraisal_template_id')->constrained('performance_appraisal_templates')->restrictOnDelete();
            $table->foreignId('rating_scale_id')->constrained('performance_rating_scales')->restrictOnDelete();
            $table->string('employment_status_snapshot')->nullable();
            $table->string('position_snapshot')->nullable();
            $table->string('grade_snapshot')->nullable();
            $table->string('department_snapshot')->nullable();
            $table->string('manager_snapshot')->nullable();
            $table->string('eligibility_status', 30)->default('eligible')->index();
            $table->text('exclusion_reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['performance_appraisal_cycle_id', 'employee_id'], 'perf_cycle_employee_unique');
        });

        Schema::create('performance_appraisal_template_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_template_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('section_type', 40)->default('custom')->index();
            $table->decimal('weight_percent', 8, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('allow_employee_rating')->default(true);
            $table->boolean('allow_manager_rating')->default(true);
            $table->boolean('allow_comment')->default(true);
            $table->timestamps();

            $table->unique(['performance_appraisal_template_id', 'code'], 'perf_template_section_code_unique');
        });

        Schema::create('performance_competency_frameworks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'code']);
        });

        Schema::create('performance_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_competency_framework_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_competency_id')->nullable()->constrained('performance_competencies')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('competency_type', 40)->default('custom')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['performance_competency_framework_id', 'code'], 'perf_competency_code_unique');
        });

        Schema::create('performance_competency_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_competency_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('behavioural_indicators')->nullable();
            $table->timestamps();

            $table->unique(['performance_competency_id', 'level_number'], 'perf_comp_level_unique');
        });

        Schema::create('performance_position_competencies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('job_title_id')->nullable();
            $table->unsignedBigInteger('grade_id')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performance_competency_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('expected_level');
            $table->decimal('weight_percent', 8, 4)->nullable();
            $table->boolean('is_required')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'effective_from', 'effective_to'], 'perf_position_comp_scope_idx');
        });

        Schema::create('performance_appraisal_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_template_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->nullable()->constrained('performance_competencies')->nullOnDelete();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('measurement_type', 30)->default('rating');
            $table->decimal('weight_percent', 8, 4)->default(0);
            $table->string('target_value')->nullable();
            $table->string('minimum_value')->nullable();
            $table->string('maximum_value')->nullable();
            $table->string('scoring_direction', 30)->default('manual');
            $table->boolean('is_required')->default(true);
            $table->boolean('allow_not_applicable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['performance_appraisal_template_section_id', 'code'], 'perf_template_item_code_unique');
        });

        Schema::create('performance_goal_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->decimal('total_weight_percent', 8, 4)->default(0);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('revision_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revision_requested_at')->nullable();
            $table->text('revision_reason')->nullable();
            $table->timestamps();

            $table->unique(['performance_appraisal_cycle_id', 'employee_id'], 'perf_goal_plan_cycle_employee_unique');
        });

        Schema::create('performance_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_goal_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('goal_type', 40)->default('individual');
            $table->string('measurement_type', 30)->default('numeric');
            $table->decimal('target_value', 18, 4)->nullable();
            $table->decimal('baseline_value', 18, 4)->nullable();
            $table->decimal('current_value', 18, 4)->nullable();
            $table->string('unit')->nullable();
            $table->string('scoring_direction', 30)->default('higher_is_better');
            $table->decimal('weight_percent', 8, 4)->default(0);
            $table->date('start_date');
            $table->date('due_date');
            $table->string('status', 40)->default('draft')->index();
            $table->decimal('progress_percent', 8, 4)->default(0);
            $table->text('employee_comment')->nullable();
            $table->text('manager_comment')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status', 'due_date'], 'perf_goal_employee_status_idx');
        });

        Schema::create('performance_goal_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_goal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('target_value', 18, 4)->nullable();
            $table->decimal('weight_percent', 8, 4)->nullable();
            $table->string('status', 40)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_goal_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_goal_id')->constrained()->cascadeOnDelete();
            $table->decimal('progress_percent', 8, 4)->default(0);
            $table->decimal('current_value', 18, 4)->nullable();
            $table->text('update_text');
            $table->string('evidence_attachment_path')->nullable();
            $table->date('update_date');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('manager_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_verified_at')->nullable();
            $table->string('verification_status', 30)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_appraisals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_cycle_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_appraisal_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('secondary_reviewer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('appraisal_template_id')->constrained('performance_appraisal_templates')->restrictOnDelete();
            $table->unsignedInteger('appraisal_template_version');
            $table->foreignId('rating_scale_id')->constrained('performance_rating_scales')->restrictOnDelete();
            $table->json('template_snapshot')->nullable();
            $table->json('rating_scale_snapshot')->nullable();
            $table->json('calculation_snapshot')->nullable();
            $table->string('status', 50)->default('draft')->index();
            $table->decimal('goal_score', 10, 4)->nullable();
            $table->decimal('competency_score', 10, 4)->nullable();
            $table->decimal('other_score', 10, 4)->nullable();
            $table->decimal('calculated_score', 10, 4)->nullable();
            $table->decimal('moderated_score', 10, 4)->nullable();
            $table->decimal('final_score', 10, 4)->nullable();
            $table->foreignId('final_rating_level_id')->nullable()->constrained('performance_rating_scale_levels')->nullOnDelete();
            $table->text('employee_overall_comment')->nullable();
            $table->text('manager_overall_comment')->nullable();
            $table->text('moderator_comment')->nullable();
            $table->text('final_comment')->nullable();
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('manager_submitted_at')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique('performance_appraisal_cycle_assignment_id', 'perf_appraisal_assignment_unique');
            $table->index(['employee_id', 'status'], 'perf_appraisal_employee_status_idx');
        });

        Schema::create('performance_appraisal_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_template_section_id')->nullable()->constrained('performance_appraisal_template_sections')->nullOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('section_type', 40);
            $table->decimal('weight_percent', 8, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('performance_appraisal_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_template_item_id')->nullable()->constrained('performance_appraisal_template_items')->nullOnDelete();
            $table->foreignId('performance_goal_id')->nullable()->constrained('performance_goals')->nullOnDelete();
            $table->foreignId('competency_id')->nullable()->constrained('performance_competencies')->nullOnDelete();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('measurement_type', 30);
            $table->decimal('weight_percent', 8, 4)->default(0);
            $table->string('expected_value')->nullable();
            $table->unsignedInteger('expected_level')->nullable();
            $table->decimal('employee_rating', 10, 4)->nullable();
            $table->decimal('manager_rating', 10, 4)->nullable();
            $table->decimal('secondary_reviewer_rating', 10, 4)->nullable();
            $table->decimal('moderated_rating', 10, 4)->nullable();
            $table->decimal('final_rating', 10, 4)->nullable();
            $table->text('employee_comment')->nullable();
            $table->text('manager_comment')->nullable();
            $table->text('secondary_reviewer_comment')->nullable();
            $table->text('moderator_comment')->nullable();
            $table->text('evidence_summary')->nullable();
            $table->boolean('is_not_applicable')->default(false);
            $table->timestamps();
        });

        Schema::create('performance_appraisal_reviewers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_type', 40)->default('custom');
            $table->string('status', 30)->default('invited');
            $table->boolean('can_rate')->default(false);
            $table->boolean('can_comment')->default(true);
            $table->boolean('is_confidential')->default(true);
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_appraisal_moderation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('scope_type', 40)->default('business');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['performance_appraisal_cycle_id', 'code'], 'perf_moderation_session_code_unique');
        });

        Schema::create('performance_appraisal_moderation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_moderation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_appraisal_id')->constrained()->cascadeOnDelete();
            $table->decimal('original_score', 10, 4);
            $table->decimal('proposed_score', 10, 4)->nullable();
            $table->decimal('moderated_score', 10, 4)->nullable();
            $table->foreignId('original_rating_level_id')->nullable()->constrained('performance_rating_scale_levels')->nullOnDelete();
            $table->foreignId('moderated_rating_level_id')->nullable()->constrained('performance_rating_scale_levels')->nullOnDelete();
            $table->text('moderation_reason')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_appraisal_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('acknowledgement_status', 40);
            $table->text('employee_comment')->nullable();
            $table->timestamp('acknowledged_at');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_appraisal_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('dispute_type', 40);
            $table->text('reason');
            $table->text('requested_resolution')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_development_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_appraisal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performance_appraisal_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->date('start_date');
            $table->date('target_completion_date');
            $table->timestamp('completed_at')->nullable();
            $table->text('overall_objective');
            $table->text('employee_comment')->nullable();
            $table->text('manager_comment')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_development_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_development_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->nullable()->constrained('performance_competencies')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('action_type', 40)->default('other');
            $table->date('target_date');
            $table->string('status', 30)->default('planned');
            $table->text('expected_outcome')->nullable();
            $table->string('completion_evidence_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_improvement_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_appraisal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('hr_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 40)->default('draft')->index();
            $table->text('reason_summary');
            $table->text('expectations_summary');
            $table->text('support_summary')->nullable();
            $table->string('review_frequency')->nullable();
            $table->timestamp('employee_acknowledged_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('outcome_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_improvement_plan_objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_improvement_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('measurement_type', 30)->default('manual');
            $table->string('target_value')->nullable();
            $table->date('due_date');
            $table->string('status', 40)->default('pending');
            $table->text('manager_comment')->nullable();
            $table->text('employee_comment')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_improvement_plan_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_improvement_plan_id')->constrained()->cascadeOnDelete();
            $table->date('review_date');
            $table->string('progress_status', 40);
            $table->text('manager_comment');
            $table->text('employee_comment')->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_probation_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('probation_start_date');
            $table->date('expected_confirmation_date');
            $table->date('review_date');
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('review_type', 30)->default('interim');
            $table->string('status', 40)->default('draft');
            $table->decimal('performance_score', 10, 4)->nullable();
            $table->json('attendance_context')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvement_areas')->nullable();
            $table->string('manager_recommendation', 40)->default('no_recommendation');
            $table->date('recommended_extension_end_date')->nullable();
            $table->string('hr_decision')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_appraisal_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_id')->constrained()->cascadeOnDelete();
            $table->string('recommendation_type', 50);
            $table->text('recommendation_text');
            $table->foreignId('recommended_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('proposed');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('implementation_reference_type')->nullable();
            $table->unsignedBigInteger('implementation_reference_id')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_appraisal_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_id')->constrained()->cascadeOnDelete();
            $table->string('amendment_number');
            $table->text('reason');
            $table->json('before_values');
            $table->json('after_values');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->unique(['performance_appraisal_id', 'amendment_number'], 'perf_appraisal_amendment_unique');
        });

        Schema::create('performance_appraisal_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_appraisal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performance_appraisal_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_appraisal_histories');
        Schema::dropIfExists('performance_appraisal_amendments');
        Schema::dropIfExists('performance_appraisal_recommendations');
        Schema::dropIfExists('performance_probation_reviews');
        Schema::dropIfExists('performance_improvement_plan_reviews');
        Schema::dropIfExists('performance_improvement_plan_objectives');
        Schema::dropIfExists('performance_improvement_plans');
        Schema::dropIfExists('performance_development_actions');
        Schema::dropIfExists('performance_development_plans');
        Schema::dropIfExists('performance_appraisal_disputes');
        Schema::dropIfExists('performance_appraisal_acknowledgements');
        Schema::dropIfExists('performance_appraisal_moderation_items');
        Schema::dropIfExists('performance_appraisal_moderation_sessions');
        Schema::dropIfExists('performance_appraisal_reviewers');
        Schema::dropIfExists('performance_appraisal_items');
        Schema::dropIfExists('performance_appraisal_sections');
        Schema::dropIfExists('performance_appraisals');
        Schema::dropIfExists('performance_goal_updates');
        Schema::dropIfExists('performance_goal_milestones');
        Schema::dropIfExists('performance_goals');
        Schema::dropIfExists('performance_goal_plans');
        Schema::dropIfExists('performance_appraisal_template_items');
        Schema::dropIfExists('performance_position_competencies');
        Schema::dropIfExists('performance_competency_levels');
        Schema::dropIfExists('performance_competencies');
        Schema::dropIfExists('performance_competency_frameworks');
        Schema::dropIfExists('performance_appraisal_template_sections');
        Schema::dropIfExists('performance_appraisal_cycle_assignments');
        Schema::dropIfExists('performance_appraisal_templates');
        Schema::dropIfExists('performance_appraisal_cycles');
        Schema::dropIfExists('performance_rating_scale_levels');
        Schema::dropIfExists('performance_rating_scales');
    }
};
