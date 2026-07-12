<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createRecruitmentRequisitions();
        $this->createRecruitmentVacancies();
        $this->createRecruitmentJobPostings();
        $this->createRecruitmentCandidates();
        $this->createRecruitmentRejectionReasons();
        $this->createRecruitmentApplications();
        $this->createRecruitmentCandidateDocuments();
        $this->createRecruitmentApplicationStageHistories();
        $this->createRecruitmentScreening();
        $this->createRecruitmentAssessments();
        $this->createRecruitmentInterviews();
        $this->createRecruitmentSelectionReviews();
        $this->createRecruitmentOffers();
        $this->createRecruitmentPreEmploymentChecks();
        $this->createRecruitmentCandidateReferences();
        $this->createRecruitmentOnboarding();
        $this->createEmployeeConfirmationDecisions();
        $this->createRecruitmentCommunications();
        $this->createRecruitmentHistories();
    }

    public function down(): void
    {
        foreach ([
            'recruitment_histories',
            'recruitment_communications',
            'employee_confirmation_decisions',
            'recruitment_onboarding_tasks',
            'recruitment_onboarding_plans',
            'recruitment_onboarding_template_tasks',
            'recruitment_onboarding_templates',
            'recruitment_candidate_references',
            'recruitment_pre_employment_checks',
            'recruitment_offers',
            'recruitment_selection_review_candidates',
            'recruitment_selection_reviews',
            'recruitment_interview_score_items',
            'recruitment_interview_scores',
            'recruitment_interview_scorecard_items',
            'recruitment_interview_scorecard_templates',
            'recruitment_interviews',
            'recruitment_interview_panel_members',
            'recruitment_interview_panels',
            'recruitment_application_assessments',
            'recruitment_assessments',
            'recruitment_application_screening_items',
            'recruitment_application_screenings',
            'recruitment_screening_criteria',
            'recruitment_screening_templates',
            'recruitment_application_stage_histories',
            'recruitment_candidate_documents',
            'recruitment_applications',
            'recruitment_rejection_reasons',
            'recruitment_candidates',
            'recruitment_job_postings',
            'recruitment_vacancies',
            'recruitment_requisitions',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createRecruitmentRequisitions(): void
    {
        if (Schema::hasTable('recruitment_requisitions')) {
            return;
        }

        Schema::create('recruitment_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requisition_number')->unique();
            $table->string('title');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('position_id')->nullable()->index();
            $table->unsignedBigInteger('job_title_id')->nullable()->index();
            $table->unsignedBigInteger('grade_id')->nullable()->index();
            $table->string('employment_type', 40);
            $table->unsignedInteger('requested_headcount');
            $table->foreignId('replacement_for_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('requisition_type', 40);
            $table->date('requested_start_date')->nullable();
            $table->decimal('budgeted_salary_min', 15, 4)->nullable();
            $table->decimal('budgeted_salary_max', 15, 4)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('budget_code')->nullable();
            $table->text('justification');
            $table->text('required_qualifications')->nullable();
            $table->text('required_experience')->nullable();
            $table->text('required_skills')->nullable();
            $table->foreignId('hiring_manager_employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('recruiter_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status']);
            $table->index(['hiring_manager_employee_id', 'status']);
        });
    }

    private function createRecruitmentVacancies(): void
    {
        if (Schema::hasTable('recruitment_vacancies')) {
            return;
        }

        Schema::create('recruitment_vacancies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recruitment_requisition_id')->constrained()->cascadeOnDelete();
            $table->string('vacancy_number')->unique();
            $table->string('title');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('position_id')->nullable()->index();
            $table->unsignedBigInteger('job_title_id')->nullable()->index();
            $table->unsignedBigInteger('grade_id')->nullable()->index();
            $table->string('employment_type', 40);
            $table->unsignedInteger('number_of_openings');
            $table->unsignedInteger('filled_openings')->default(0);
            $table->foreignId('recruiter_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('hiring_manager_employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('opening_date');
            $table->date('target_closing_date')->nullable();
            $table->date('actual_closing_date')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('visibility', 40)->default('internal_and_external')->index();
            $table->text('description');
            $table->text('responsibilities')->nullable();
            $table->text('minimum_qualifications')->nullable();
            $table->text('preferred_qualifications')->nullable();
            $table->text('experience_requirements')->nullable();
            $table->text('skills_requirements')->nullable();
            $table->string('salary_display_type', 30)->default('hidden');
            $table->decimal('salary_min', 15, 4)->nullable();
            $table->decimal('salary_max', 15, 4)->nullable();
            $table->string('currency', 10)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['recruitment_requisition_id', 'status']);
            $table->index(['department_id', 'status']);
        });
    }

    private function createRecruitmentJobPostings(): void
    {
        if (Schema::hasTable('recruitment_job_postings')) {
            return;
        }

        Schema::create('recruitment_job_postings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recruitment_vacancy_id')->constrained()->cascadeOnDelete();
            $table->string('channel_type', 40);
            $table->string('channel_name')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('posting_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamps();
            $table->index(['recruitment_vacancy_id', 'status']);
        });
    }

    private function createRecruitmentCandidates(): void
    {
        if (Schema::hasTable('recruitment_candidates')) {
            return;
        }

        Schema::create('recruitment_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('candidate_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('nationality')->nullable();
            $table->string('current_location')->nullable();
            $table->text('address')->nullable();
            $table->string('current_employer')->nullable();
            $table->string('current_job_title')->nullable();
            $table->decimal('total_experience_years', 6, 2)->nullable();
            $table->string('highest_qualification')->nullable();
            $table->text('professional_summary')->nullable();
            $table->string('source', 40)->default('direct')->index();
            $table->foreignId('referred_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('candidate_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consent_given_at')->nullable();
            $table->string('privacy_notice_version')->nullable();
            $table->date('data_retention_until')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->text('blacklist_reason')->nullable();
            $table->foreignId('duplicate_of_candidate_id')->nullable()->constrained('recruitment_candidates')->nullOnDelete();
            $table->timestamps();
        });
    }

    private function createRecruitmentApplications(): void
    {
        if (Schema::hasTable('recruitment_applications')) {
            return;
        }

        Schema::create('recruitment_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recruitment_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recruitment_vacancy_id')->constrained()->restrictOnDelete();
            $table->string('application_number')->unique();
            $table->date('application_date');
            $table->foreignId('source_posting_id')->nullable()->constrained('recruitment_job_postings')->nullOnDelete();
            $table->string('current_stage', 40)->default('applied')->index();
            $table->string('status', 30)->default('active')->index();
            $table->text('cover_letter')->nullable();
            $table->decimal('expected_salary', 15, 4)->nullable();
            $table->string('currency', 10)->nullable();
            $table->date('available_start_date')->nullable();
            $table->unsignedInteger('notice_period_days')->nullable();
            $table->foreignId('internal_candidate_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('assigned_recruiter_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('hiring_manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('recruitment_rejection_reasons')->nullOnDelete();
            $table->text('rejection_notes')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->foreignId('hired_employee_id')->nullable()->unique()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->unique(['recruitment_candidate_id', 'recruitment_vacancy_id']);
            $table->index(['recruitment_vacancy_id', 'current_stage', 'status'], 'recruitment_applications_vacancy_stage_status_index');
        });
    }

    private function createRecruitmentCandidateDocuments(): void
    {
        if (Schema::hasTable('recruitment_candidate_documents')) {
            return;
        }

        Schema::create('recruitment_candidate_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recruitment_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recruitment_application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('document_type', 60);
            $table->string('title');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('document_number')->nullable();
            $table->string('issued_by')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('verification_status', 30)->default('unverified')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_confidential')->default(false)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    private function createRecruitmentApplicationStageHistories(): void
    {
        if (Schema::hasTable('recruitment_application_stage_histories')) {
            return;
        }

        Schema::create('recruitment_application_stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 40)->nullable();
            $table->string('to_stage', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['recruitment_application_id', 'changed_at'], 'recruitment_stage_history_application_changed_index');
        });
    }

    private function createRecruitmentScreening(): void
    {
        if (! Schema::hasTable('recruitment_screening_templates')) {
            Schema::create('recruitment_screening_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
                $table->string('code');
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('applicable_position_id')->nullable()->index();
                $table->unsignedBigInteger('applicable_job_title_id')->nullable()->index();
                $table->foreignId('applicable_department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->boolean('is_active')->default(true)->index();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['code', 'version']);
            });
        }

        if (! Schema::hasTable('recruitment_screening_criteria')) {
            Schema::create('recruitment_screening_criteria', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_screening_template_id')->constrained()->cascadeOnDelete();
                $table->string('code');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('criterion_type', 40);
                $table->string('evaluation_type', 40);
                $table->decimal('weight_percent', 8, 4)->nullable();
                $table->boolean('is_mandatory')->default(false);
                $table->boolean('disqualifying_if_failed')->default(false);
                $table->decimal('minimum_value', 15, 4)->nullable();
                $table->decimal('maximum_value', 15, 4)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['recruitment_screening_template_id', 'code'], 'recruitment_screening_criteria_template_code_unique');
            });
        }

        if (! Schema::hasTable('recruitment_application_screenings')) {
            Schema::create('recruitment_application_screenings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
                $table->foreignId('screening_template_id')->constrained('recruitment_screening_templates')->restrictOnDelete();
                $table->unsignedInteger('screening_template_version');
                $table->foreignId('screened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 30)->default('pending')->index();
                $table->decimal('total_score', 12, 4)->nullable();
                $table->boolean('mandatory_criteria_passed')->nullable();
                $table->string('recommendation', 30)->nullable();
                $table->string('override_recommendation', 30)->nullable();
                $table->text('override_reason')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['recruitment_application_id', 'screening_template_id'], 'recruitment_application_screenings_application_template_unique');
            });
        }

        if (! Schema::hasTable('recruitment_application_screening_items')) {
            Schema::create('recruitment_application_screening_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_application_screening_id')->constrained()->cascadeOnDelete();
                $table->foreignId('source_criterion_id')->nullable()->constrained('recruitment_screening_criteria')->nullOnDelete();
                $table->string('code');
                $table->string('title');
                $table->string('criterion_type', 40);
                $table->decimal('weight_percent', 8, 4)->nullable();
                $table->boolean('is_mandatory')->default(false);
                $table->boolean('disqualifying_if_failed')->default(false);
                $table->text('result_value')->nullable();
                $table->boolean('passed')->nullable();
                $table->decimal('score', 12, 4)->nullable();
                $table->text('reviewer_comment')->nullable();
                $table->foreignId('evidence_document_id')->nullable()->constrained('recruitment_candidate_documents')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    private function createRecruitmentAssessments(): void
    {
        if (! Schema::hasTable('recruitment_assessments')) {
            Schema::create('recruitment_assessments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_vacancy_id')->constrained()->cascadeOnDelete();
                $table->string('code');
                $table->string('title');
                $table->string('assessment_type', 40);
                $table->text('description')->nullable();
                $table->decimal('maximum_score', 12, 4)->nullable();
                $table->decimal('passing_score', 12, 4)->nullable();
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->text('instructions')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->unique(['recruitment_vacancy_id', 'code']);
            });
        }

        if (! Schema::hasTable('recruitment_application_assessments')) {
            Schema::create('recruitment_application_assessments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recruitment_assessment_id')->constrained()->cascadeOnDelete();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->decimal('score', 12, 4)->nullable();
                $table->string('result', 30)->default('pending')->index();
                $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('comment')->nullable();
                $table->string('evidence_path')->nullable();
                $table->timestamps();
                $table->unique(['recruitment_application_id', 'recruitment_assessment_id'], 'recruitment_application_assessments_application_assessment_unique');
            });
        }
    }

    private function createRecruitmentInterviews(): void
    {
        if (! Schema::hasTable('recruitment_interview_panels')) {
            Schema::create('recruitment_interview_panels', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_vacancy_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('chair_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recruitment_interview_panel_members')) {
            Schema::create('recruitment_interview_panel_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_interview_panel_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('panel_role', 40)->default('observer');
                $table->boolean('can_score')->default(false);
                $table->boolean('can_comment')->default(true);
                $table->boolean('is_confidential')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recruitment_interviews')) {
            Schema::create('recruitment_interviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recruitment_interview_panel_id')->constrained()->restrictOnDelete();
                $table->string('interview_type', 40);
                $table->unsignedInteger('round_number')->default(1);
                $table->timestamp('scheduled_start');
                $table->timestamp('scheduled_end');
                $table->string('timezone')->default('UTC');
                $table->string('location')->nullable();
                $table->string('meeting_link')->nullable();
                $table->string('status', 30)->default('scheduled')->index();
                $table->timestamp('candidate_confirmed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->index(['recruitment_application_id', 'scheduled_start'], 'recruitment_interviews_application_start_index');
            });
        }

        if (! Schema::hasTable('recruitment_interview_scorecard_templates')) {
            Schema::create('recruitment_interview_scorecard_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
                $table->string('code');
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('applicable_position_id')->nullable()->index();
                $table->unsignedBigInteger('applicable_job_title_id')->nullable()->index();
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['code', 'version']);
            });
        }

        if (! Schema::hasTable('recruitment_interview_scorecard_items')) {
            Schema::create('recruitment_interview_scorecard_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_interview_scorecard_template_id')->constrained()->cascadeOnDelete();
                $table->string('code');
                $table->string('title');
                $table->text('description')->nullable();
                $table->foreignId('competency_id')->nullable()->constrained('performance_competencies')->nullOnDelete();
                $table->decimal('weight_percent', 8, 4);
                $table->decimal('maximum_score', 12, 4);
                $table->boolean('is_required')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recruitment_interview_scores')) {
            Schema::create('recruitment_interview_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_interview_id')->constrained()->cascadeOnDelete();
                $table->foreignId('reviewer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('scorecard_template_id')->constrained('recruitment_interview_scorecard_templates')->restrictOnDelete();
                $table->unsignedInteger('scorecard_template_version');
                $table->decimal('total_score', 12, 4)->nullable();
                $table->string('recommendation', 50)->nullable();
                $table->text('overall_comment')->nullable();
                $table->string('status', 30)->default('draft')->index();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->unique(['recruitment_interview_id', 'reviewer_employee_id', 'reviewer_user_id'], 'recruitment_interview_scores_unique_reviewer');
            });
        }

        if (! Schema::hasTable('recruitment_interview_score_items')) {
            Schema::create('recruitment_interview_score_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_interview_score_id')->constrained()->cascadeOnDelete();
                $table->foreignId('source_scorecard_item_id')->nullable()->constrained('recruitment_interview_scorecard_items')->nullOnDelete();
                $table->string('code');
                $table->string('title');
                $table->decimal('weight_percent', 8, 4);
                $table->decimal('maximum_score', 12, 4);
                $table->decimal('score', 12, 4);
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createRecruitmentSelectionReviews(): void
    {
        if (! Schema::hasTable('recruitment_selection_reviews')) {
            Schema::create('recruitment_selection_reviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_vacancy_id')->constrained()->cascadeOnDelete();
                $table->string('status', 30)->default('draft')->index();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('summary')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recruitment_selection_review_candidates')) {
            Schema::create('recruitment_selection_review_candidates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_selection_review_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
                $table->decimal('screening_score', 12, 4)->nullable();
                $table->decimal('assessment_score', 12, 4)->nullable();
                $table->decimal('interview_score', 12, 4)->nullable();
                $table->decimal('combined_score', 12, 4)->nullable();
                $table->unsignedInteger('rank')->nullable();
                $table->string('panel_recommendation')->nullable();
                $table->string('hiring_manager_recommendation')->nullable();
                $table->string('hr_recommendation')->nullable();
                $table->string('final_recommendation', 30)->nullable();
                $table->text('justification')->nullable();
                $table->boolean('conflict_of_interest_declared')->default(false);
                $table->timestamps();
                $table->unique(['recruitment_selection_review_id', 'recruitment_application_id'], 'recruitment_selection_review_candidates_unique');
            });
        }

    }

    private function createRecruitmentRejectionReasons(): void
    {
        if (Schema::hasTable('recruitment_rejection_reasons')) {
            return;
        }

        Schema::create('recruitment_rejection_reasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('candidate_visible_message')->nullable();
            $table->text('internal_description')->nullable();
            $table->string('stage')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'code']);
        });
    }

    private function createRecruitmentOffers(): void
    {
        if (Schema::hasTable('recruitment_offers')) {
            return;
        }

        Schema::create('recruitment_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
            $table->string('offer_number')->unique();
            $table->unsignedInteger('offer_version')->default(1);
            $table->unsignedBigInteger('position_id')->nullable()->index();
            $table->unsignedBigInteger('job_title_id')->nullable()->index();
            $table->unsignedBigInteger('grade_id')->nullable()->index();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employment_type', 40);
            $table->date('proposed_start_date');
            $table->unsignedInteger('probation_months')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->decimal('base_salary', 15, 4)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('pay_frequency')->nullable();
            $table->json('allowance_summary')->nullable();
            $table->json('benefit_summary')->nullable();
            $table->foreignId('reporting_manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->date('valid_until');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->timestamps();
            $table->index(['recruitment_application_id', 'status']);
        });
    }

    private function createRecruitmentPreEmploymentChecks(): void
    {
        if (Schema::hasTable('recruitment_pre_employment_checks')) {
            return;
        }

        Schema::create('recruitment_pre_employment_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recruitment_application_id')->constrained()->cascadeOnDelete();
            $table->string('check_type', 60);
            $table->string('status', 30)->default('not_started')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('result_summary')->nullable();
            $table->text('confidential_notes')->nullable();
            $table->string('evidence_path')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->timestamps();
            $table->index(['recruitment_application_id', 'check_type']);
        });
    }

    private function createRecruitmentCandidateReferences(): void
    {
        if (Schema::hasTable('recruitment_candidate_references')) {
            return;
        }

        Schema::create('recruitment_candidate_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recruitment_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recruitment_application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('organization')->nullable();
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('years_known', 6, 2)->nullable();
            $table->boolean('consent_confirmed')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('response_summary')->nullable();
            $table->text('confidential_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    private function createRecruitmentOnboarding(): void
    {
        if (! Schema::hasTable('recruitment_onboarding_templates')) {
            Schema::create('recruitment_onboarding_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
                $table->string('code');
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('applicable_department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->unsignedBigInteger('applicable_position_id')->nullable()->index();
                $table->string('applicable_employment_type')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->unique(['code', 'version']);
            });
        }

        if (! Schema::hasTable('recruitment_onboarding_template_tasks')) {
            Schema::create('recruitment_onboarding_template_tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_onboarding_template_id')->constrained()->cascadeOnDelete();
                $table->string('code');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('task_category', 40);
                $table->string('responsible_role_type', 40);
                $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->integer('due_offset_days')->default(0);
                $table->boolean('is_required')->default(true);
                $table->boolean('requires_attachment')->default(false);
                $table->boolean('requires_approval')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recruitment_onboarding_plans')) {
            Schema::create('recruitment_onboarding_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recruitment_application_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('recruitment_offer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('onboarding_template_id')->nullable()->constrained('recruitment_onboarding_templates')->nullOnDelete();
                $table->unsignedInteger('onboarding_template_version')->nullable();
                $table->date('start_date');
                $table->date('target_completion_date');
                $table->string('status', 30)->default('draft')->index();
                $table->foreignId('assigned_hr_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->decimal('progress_percent', 8, 4)->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('recruitment_onboarding_tasks')) {
            Schema::create('recruitment_onboarding_tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recruitment_onboarding_plan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('source_template_task_id')->nullable()->constrained('recruitment_onboarding_template_tasks')->nullOnDelete();
                $table->string('code');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('task_category', 40);
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->date('due_date');
                $table->string('status', 30)->default('pending')->index();
                $table->boolean('is_required')->default(true);
                $table->boolean('requires_attachment')->default(false);
                $table->boolean('requires_approval')->default(false);
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('waiver_reason')->nullable();
                $table->string('evidence_path')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createEmployeeConfirmationDecisions(): void
    {
        if (Schema::hasTable('employee_confirmation_decisions')) {
            return;
        }

        Schema::create('employee_confirmation_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_probation_review_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision_type', 40);
            $table->date('proposed_effective_date');
            $table->date('proposed_extension_end_date')->nullable();
            $table->text('reason');
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('implemented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('implemented_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });
    }

    private function createRecruitmentCommunications(): void
    {
        if (Schema::hasTable('recruitment_communications')) {
            return;
        }

        Schema::create('recruitment_communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recruitment_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recruitment_application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('communication_type', 60);
            $table->string('channel', 40);
            $table->string('subject');
            $table->text('body_snapshot')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->string('delivery_status', 30)->default('draft');
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    private function createRecruitmentHistories(): void
    {
        if (Schema::hasTable('recruitment_histories')) {
            return;
        }

        Schema::create('recruitment_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 60)->index();
            $table->string('action', 80)->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recruitment_requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recruitment_vacancy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recruitment_candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recruitment_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }
};
