<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Business;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeConfirmationDecision;
use App\Models\Location;
use App\Models\Manufacturing\WorkCenter;
use App\Models\PerformanceProbationReview;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentApplicationScreening;
use App\Models\RecruitmentAssessment;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentHistory;
use App\Models\RecruitmentInterview;
use App\Models\RecruitmentInterviewPanel;
use App\Models\RecruitmentInterviewScorecardTemplate;
use App\Models\RecruitmentJobPosting;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentOnboardingPlan;
use App\Models\RecruitmentOnboardingTask;
use App\Models\RecruitmentOnboardingTemplate;
use App\Models\RecruitmentOnboardingTemplateTask;
use App\Models\RecruitmentPreEmploymentCheck;
use App\Models\RecruitmentRejectionReason;
use App\Models\RecruitmentRequisition;
use App\Models\RecruitmentScreeningTemplate;
use App\Models\RecruitmentSelectionReview;
use App\Models\RecruitmentVacancy;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecruitmentResourceSchema
{
    /**
     * @var array<string, array<int|string, string>>
     */
    private static array $optionCache = [];

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function form(Schema $schema, string $modelClass): Schema
    {
        $config = self::config($modelClass);

        return $schema->components(array_map(
            fn (array $section): Section => Section::make($section['label'])
                ->icon($section['icon'] ?? null)
                ->columns($section['columns'] ?? ['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema(array_map(
                    fn (string $field): object => self::formField($field, $modelClass),
                    $section['fields'],
                )),
            $config['sections'],
        ));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function infolist(Schema $schema, string $modelClass): Schema
    {
        $config = self::config($modelClass);

        return $schema->components([
            Grid::make(['default' => 1, 'xl' => 2])
                ->schema(array_map(
                    fn (array $section): Section => Section::make($section['label'])
                        ->icon($section['icon'] ?? null)
                        ->columns($section['columns'] ?? ['default' => 1, 'md' => 2])
                        ->schema(array_map(
                            fn (string $field): object => self::infolistEntry($field),
                            $section['fields'],
                        )),
                    $config['sections'],
                )),
        ]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function table(Table $table, string $modelClass): Table
    {
        $config = self::config($modelClass);

        return $table
            ->columns(array_map(
                fn (string $field): object => self::tableColumn($field),
                $config['table'],
            ))
            ->filters(self::filters($config), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions(self::recordActions($modelClass))
            ->toolbarActions([])
            ->defaultSort($config['defaultSort'] ?? 'updated_at', $config['defaultSortDirection'] ?? 'desc')
            ->emptyStateHeading('No '.Str::headline(class_basename($modelClass)).' records')
            ->emptyStateDescription('Records will appear here as the recruitment workflow progresses.');
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, mixed>
     */
    private static function config(string $modelClass): array
    {
        return match ($modelClass) {
            RecruitmentRequisition::class => [
                'sections' => [
                    ['label' => 'Requisition Details', 'icon' => 'heroicon-o-document-text', 'fields' => ['business_id', 'requisition_number', 'title', 'requisition_type', 'priority', 'status']],
                    ['label' => 'Position and Department', 'icon' => 'heroicon-o-building-office-2', 'fields' => ['department_id', 'work_center_id', 'location_id', 'position_id', 'job_title_id', 'grade_id']],
                    ['label' => 'Headcount and Terms', 'icon' => 'heroicon-o-user-group', 'fields' => ['employment_type', 'requested_headcount', 'replacement_for_employee_id', 'hiring_manager_employee_id', 'recruiter_employee_id', 'requested_start_date']],
                    ['label' => 'Budget and Requirements', 'icon' => 'heroicon-o-banknotes', 'fields' => ['budgeted_salary_min', 'budgeted_salary_max', 'currency', 'budget_code', 'justification', 'required_qualifications', 'required_experience', 'required_skills']],
                    ['label' => 'Approval Workflow', 'icon' => 'heroicon-o-check-badge', 'fields' => ['submitted_at', 'approved_at', 'rejected_at', 'rejection_reason', 'cancelled_at', 'cancellation_reason']],
                ],
                'table' => ['requisition_number', 'title', 'department_id', 'requested_headcount', 'employment_type', 'priority', 'status', 'hiring_manager_employee_id', 'requested_start_date', 'approved_at'],
                'filters' => ['business_id', 'department_id', 'employment_type', 'priority', 'status', 'hiring_manager_employee_id'],
            ],
            RecruitmentVacancy::class => [
                'sections' => [
                    ['label' => 'Vacancy Details', 'icon' => 'heroicon-o-briefcase', 'fields' => ['recruitment_requisition_id', 'vacancy_number', 'title', 'status', 'visibility']],
                    ['label' => 'Position Scope', 'icon' => 'heroicon-o-building-office', 'fields' => ['department_id', 'work_center_id', 'location_id', 'position_id', 'job_title_id', 'grade_id', 'employment_type']],
                    ['label' => 'Openings and Hiring Team', 'icon' => 'heroicon-o-users', 'fields' => ['number_of_openings', 'filled_openings', 'recruiter_employee_id', 'hiring_manager_employee_id']],
                    ['label' => 'Application Period and Description', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['opening_date', 'target_closing_date', 'actual_closing_date', 'description', 'responsibilities', 'minimum_qualifications', 'preferred_qualifications', 'experience_requirements', 'skills_requirements']],
                    ['label' => 'Salary Display', 'icon' => 'heroicon-o-banknotes', 'fields' => ['salary_display_type', 'salary_min', 'salary_max', 'currency']],
                ],
                'table' => ['vacancy_number', 'title', 'department_id', 'employment_type', 'number_of_openings', 'filled_openings', 'status', 'visibility', 'opening_date', 'target_closing_date'],
                'filters' => ['department_id', 'employment_type', 'status', 'visibility', 'hiring_manager_employee_id', 'recruiter_employee_id'],
            ],
            RecruitmentJobPosting::class => [
                'sections' => [
                    ['label' => 'Posting Channel', 'icon' => 'heroicon-o-megaphone', 'fields' => ['recruitment_vacancy_id', 'channel_type', 'channel_name', 'external_reference', 'posting_url']],
                    ['label' => 'Publication Lifecycle', 'icon' => 'heroicon-o-calendar', 'fields' => ['status', 'published_at', 'closes_at', 'published_by', 'withdrawn_at', 'withdrawal_reason']],
                ],
                'table' => ['recruitment_vacancy_id', 'channel_type', 'channel_name', 'status', 'published_at', 'closes_at', 'updated_at'],
                'filters' => ['recruitment_vacancy_id', 'channel_type', 'status'],
            ],
            RecruitmentCandidate::class => [
                'sections' => [
                    ['label' => 'Personal Information', 'icon' => 'heroicon-o-user', 'fields' => ['candidate_number', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'nationality', 'status']],
                    ['label' => 'Contact Information', 'icon' => 'heroicon-o-envelope', 'fields' => ['email', 'phone', 'alternate_phone', 'current_location', 'address']],
                    ['label' => 'Professional Profile', 'icon' => 'heroicon-o-academic-cap', 'fields' => ['current_employer', 'current_job_title', 'total_experience_years', 'highest_qualification', 'professional_summary']],
                    ['label' => 'Source and Privacy', 'icon' => 'heroicon-o-shield-check', 'fields' => ['source', 'referred_by_employee_id', 'candidate_user_id', 'consent_given_at', 'privacy_notice_version', 'data_retention_until', 'duplicate_of_candidate_id', 'blacklist_reason']],
                ],
                'table' => ['candidate_number', 'first_name', 'last_name', 'email', 'phone', 'current_job_title', 'source', 'status', 'created_at'],
                'filters' => ['source', 'status', 'referred_by_employee_id'],
                'defaultSort' => 'last_name',
                'defaultSortDirection' => 'asc',
            ],
            RecruitmentApplication::class => [
                'sections' => [
                    ['label' => 'Application Context', 'icon' => 'heroicon-o-clipboard-document-list', 'fields' => ['application_number', 'recruitment_candidate_id', 'recruitment_vacancy_id', 'application_date', 'source_posting_id', 'current_stage', 'status']],
                    ['label' => 'Candidate Terms', 'icon' => 'heroicon-o-banknotes', 'fields' => ['expected_salary', 'currency', 'available_start_date', 'notice_period_days', 'cover_letter']],
                    ['label' => 'Recruitment Ownership', 'icon' => 'heroicon-o-users', 'fields' => ['internal_candidate_employee_id', 'assigned_recruiter_employee_id', 'hiring_manager_employee_id']],
                    ['label' => 'Outcome', 'icon' => 'heroicon-o-check-badge', 'fields' => ['rejection_reason_id', 'rejection_notes', 'withdrawn_at', 'withdrawal_reason', 'hired_employee_id']],
                ],
                'table' => ['application_number', 'recruitment_candidate_id', 'recruitment_vacancy_id', 'current_stage', 'status', 'application_date', 'assigned_recruiter_employee_id', 'hiring_manager_employee_id'],
                'filters' => ['recruitment_vacancy_id', 'current_stage', 'status', 'assigned_recruiter_employee_id', 'hiring_manager_employee_id'],
            ],
            RecruitmentApplicationScreening::class => [
                'sections' => [
                    ['label' => 'Screening Context', 'icon' => 'heroicon-o-funnel', 'fields' => ['recruitment_application_id', 'screening_template_id', 'screening_template_version', 'screened_by', 'status']],
                    ['label' => 'Outcome', 'icon' => 'heroicon-o-chart-bar', 'fields' => ['total_score', 'mandatory_criteria_passed', 'recommendation', 'override_recommendation', 'override_reason', 'completed_at']],
                ],
                'table' => ['recruitment_application_id', 'screening_template_id', 'status', 'total_score', 'mandatory_criteria_passed', 'recommendation', 'completed_at'],
                'filters' => ['screening_template_id', 'status', 'recommendation', 'screened_by'],
            ],
            RecruitmentAssessment::class => [
                'sections' => [
                    ['label' => 'Assessment Details', 'icon' => 'heroicon-o-document-check', 'fields' => ['recruitment_vacancy_id', 'code', 'title', 'assessment_type', 'is_active']],
                    ['label' => 'Scoring and Instructions', 'icon' => 'heroicon-o-calculator', 'fields' => ['maximum_score', 'passing_score', 'duration_minutes', 'description', 'instructions']],
                ],
                'table' => ['code', 'title', 'recruitment_vacancy_id', 'assessment_type', 'maximum_score', 'passing_score', 'duration_minutes', 'is_active'],
                'filters' => ['recruitment_vacancy_id', 'assessment_type', 'is_active'],
            ],
            RecruitmentInterview::class => [
                'sections' => [
                    ['label' => 'Interview Context', 'icon' => 'heroicon-o-chat-bubble-left-right', 'fields' => ['recruitment_application_id', 'recruitment_interview_panel_id', 'interview_type', 'round_number', 'status']],
                    ['label' => 'Schedule and Venue', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['scheduled_start', 'scheduled_end', 'timezone', 'location', 'meeting_link']],
                    ['label' => 'Lifecycle', 'icon' => 'heroicon-o-check-circle', 'fields' => ['candidate_confirmed_at', 'completed_at', 'cancellation_reason', 'created_by']],
                ],
                'table' => ['recruitment_application_id', 'recruitment_interview_panel_id', 'interview_type', 'round_number', 'status', 'scheduled_start', 'scheduled_end'],
                'filters' => ['recruitment_interview_panel_id', 'interview_type', 'status'],
                'defaultSort' => 'scheduled_start',
            ],
            RecruitmentInterviewPanel::class => [
                'sections' => [
                    ['label' => 'Interview Panel', 'icon' => 'heroicon-o-user-group', 'fields' => ['recruitment_vacancy_id', 'name', 'chair_employee_id', 'is_active', 'description']],
                ],
                'table' => ['name', 'recruitment_vacancy_id', 'chair_employee_id', 'is_active', 'updated_at'],
                'filters' => ['recruitment_vacancy_id', 'chair_employee_id', 'is_active'],
            ],
            RecruitmentInterviewScorecardTemplate::class => [
                'sections' => [
                    ['label' => 'Scorecard Template', 'icon' => 'heroicon-o-clipboard-document-check', 'fields' => ['business_id', 'code', 'name', 'version', 'is_active', 'description']],
                    ['label' => 'Applicability', 'icon' => 'heroicon-o-briefcase', 'fields' => ['applicable_position_id', 'applicable_job_title_id']],
                ],
                'table' => ['code', 'name', 'business_id', 'version', 'is_active', 'updated_at'],
                'filters' => ['business_id', 'is_active'],
            ],
            RecruitmentSelectionReview::class => [
                'sections' => [
                    ['label' => 'Selection Review', 'icon' => 'heroicon-o-scale', 'fields' => ['recruitment_vacancy_id', 'status', 'reviewed_by', 'approved_by', 'approved_at', 'summary']],
                ],
                'table' => ['recruitment_vacancy_id', 'status', 'reviewed_by', 'approved_by', 'approved_at', 'updated_at'],
                'filters' => ['recruitment_vacancy_id', 'status', 'reviewed_by', 'approved_by'],
            ],
            RecruitmentOffer::class => [
                'sections' => [
                    ['label' => 'Candidate and Vacancy', 'icon' => 'heroicon-o-document-text', 'fields' => ['recruitment_application_id', 'offer_number', 'offer_version', 'status']],
                    ['label' => 'Offer Terms', 'icon' => 'heroicon-o-briefcase', 'fields' => ['department_id', 'work_center_id', 'location_id', 'position_id', 'job_title_id', 'grade_id', 'employment_type', 'reporting_manager_employee_id']],
                    ['label' => 'Compensation and Dates', 'icon' => 'heroicon-o-banknotes', 'fields' => ['proposed_start_date', 'probation_months', 'contract_end_date', 'base_salary', 'currency', 'pay_frequency', 'valid_until']],
                    ['label' => 'Acceptance and Approval', 'icon' => 'heroicon-o-check-badge', 'fields' => ['approved_at', 'issued_at', 'accepted_at', 'declined_at', 'decline_reason', 'withdrawn_at', 'withdrawal_reason']],
                ],
                'table' => ['offer_number', 'recruitment_application_id', 'department_id', 'employment_type', 'base_salary', 'currency', 'status', 'valid_until', 'accepted_at'],
                'filters' => ['department_id', 'employment_type', 'status', 'approved_by', 'issued_by'],
            ],
            RecruitmentPreEmploymentCheck::class => [
                'sections' => [
                    ['label' => 'Pre-Employment Check', 'icon' => 'heroicon-o-shield-check', 'fields' => ['recruitment_application_id', 'check_type', 'status', 'requested_by', 'requested_at', 'completed_by', 'completed_at']],
                    ['label' => 'Outcome and Evidence', 'icon' => 'heroicon-o-document-magnifying-glass', 'fields' => ['result_summary', 'confidential_notes', 'evidence_path', 'expires_at', 'waiver_reason']],
                ],
                'table' => ['recruitment_application_id', 'check_type', 'status', 'requested_at', 'completed_at', 'expires_at'],
                'filters' => ['check_type', 'status', 'requested_by', 'completed_by'],
            ],
            RecruitmentOnboardingTemplate::class => [
                'sections' => [
                    ['label' => 'Template Details', 'icon' => 'heroicon-o-list-bullet', 'fields' => ['business_id', 'code', 'name', 'version', 'is_active', 'description']],
                    ['label' => 'Applicability', 'icon' => 'heroicon-o-user-group', 'fields' => ['applicable_department_id', 'applicable_position_id', 'applicable_employment_type']],
                ],
                'table' => ['code', 'name', 'business_id', 'applicable_department_id', 'applicable_employment_type', 'version', 'is_active'],
                'filters' => ['business_id', 'applicable_department_id', 'applicable_employment_type', 'is_active'],
            ],
            RecruitmentOnboardingPlan::class => [
                'sections' => [
                    ['label' => 'Onboarding Plan', 'icon' => 'heroicon-o-map', 'fields' => ['employee_id', 'recruitment_application_id', 'recruitment_offer_id', 'onboarding_template_id', 'onboarding_template_version', 'status']],
                    ['label' => 'Ownership and Progress', 'icon' => 'heroicon-o-chart-pie', 'fields' => ['assigned_hr_employee_id', 'manager_employee_id', 'start_date', 'target_completion_date', 'progress_percent', 'completed_at', 'notes']],
                ],
                'table' => ['employee_id', 'onboarding_template_id', 'assigned_hr_employee_id', 'manager_employee_id', 'status', 'progress_percent', 'start_date', 'target_completion_date', 'completed_at'],
                'filters' => ['employee_id', 'onboarding_template_id', 'assigned_hr_employee_id', 'manager_employee_id', 'status'],
            ],
            RecruitmentOnboardingTask::class => [
                'sections' => [
                    ['label' => 'Task Details', 'icon' => 'heroicon-o-check-circle', 'fields' => ['recruitment_onboarding_plan_id', 'source_template_task_id', 'code', 'title', 'task_category', 'status', 'description']],
                    ['label' => 'Assignment and Due Date', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['assigned_user_id', 'assigned_employee_id', 'due_date', 'is_required', 'requires_attachment', 'requires_approval']],
                    ['label' => 'Completion and Approval', 'icon' => 'heroicon-o-check-badge', 'fields' => ['completed_at', 'approved_at', 'rejection_reason', 'waiver_reason', 'evidence_path']],
                ],
                'table' => ['title', 'recruitment_onboarding_plan_id', 'task_category', 'assigned_user_id', 'assigned_employee_id', 'due_date', 'status', 'completed_at', 'approved_at'],
                'filters' => ['recruitment_onboarding_plan_id', 'task_category', 'status', 'assigned_user_id', 'assigned_employee_id'],
            ],
            RecruitmentScreeningTemplate::class => [
                'sections' => [
                    ['label' => 'Screening Template', 'icon' => 'heroicon-o-funnel', 'fields' => ['business_id', 'code', 'name', 'version', 'is_active', 'description']],
                    ['label' => 'Applicability and Dates', 'icon' => 'heroicon-o-briefcase', 'fields' => ['applicable_position_id', 'applicable_job_title_id', 'applicable_department_id', 'effective_from', 'effective_to']],
                ],
                'table' => ['code', 'name', 'business_id', 'applicable_department_id', 'version', 'is_active', 'effective_from', 'effective_to'],
                'filters' => ['business_id', 'applicable_department_id', 'is_active'],
            ],
            RecruitmentHistory::class => [
                'sections' => [
                    ['label' => 'History Event', 'icon' => 'heroicon-o-clock', 'fields' => ['event_type', 'action', 'actor_id', 'occurred_at', 'reason']],
                    ['label' => 'Recruitment Context', 'icon' => 'heroicon-o-link', 'fields' => ['recruitment_requisition_id', 'recruitment_vacancy_id', 'recruitment_candidate_id', 'recruitment_application_id', 'employee_id']],
                    ['label' => 'Recorded Values', 'icon' => 'heroicon-o-code-bracket-square', 'fields' => ['old_values', 'new_values', 'metadata']],
                ],
                'table' => ['event_type', 'action', 'actor_id', 'recruitment_candidate_id', 'recruitment_application_id', 'occurred_at', 'created_at'],
                'filters' => ['event_type', 'action', 'actor_id', 'recruitment_vacancy_id', 'recruitment_candidate_id'],
                'readOnly' => true,
                'defaultSort' => 'occurred_at',
            ],
            EmployeeConfirmationDecision::class => [
                'sections' => [
                    ['label' => 'Confirmation Decision', 'icon' => 'heroicon-o-check-badge', 'fields' => ['employee_id', 'performance_probation_review_id', 'decision_type', 'status']],
                    ['label' => 'Effective Dates and Reason', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['proposed_effective_date', 'proposed_extension_end_date', 'reason']],
                    ['label' => 'Approval and Implementation', 'icon' => 'heroicon-o-shield-check', 'fields' => ['submitted_by', 'approved_by', 'approved_at', 'implemented_by', 'implemented_at', 'rejection_reason']],
                ],
                'table' => ['employee_id', 'decision_type', 'status', 'proposed_effective_date', 'proposed_extension_end_date', 'approved_at', 'implemented_at'],
                'filters' => ['employee_id', 'decision_type', 'status', 'submitted_by', 'approved_by', 'implemented_by'],
            ],
        };
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function formField(string $field, string $modelClass): object
    {
        if (self::isLifecycleField($field) || in_array($field, ['created_at', 'updated_at'], true)) {
            return self::dateTimeField($field)->disabled()->dehydrated(false);
        }

        if (self::isForeignKey($field)) {
            return Select::make($field)
                ->label(self::label($field))
                ->options(fn (): array => self::optionsFor($field))
                ->searchable()
                ->preload()
                ->native(false)
                ->required(self::isRequired($field, $modelClass))
                ->nullable(! self::isRequired($field, $modelClass));
        }

        if (self::hasOptions($field)) {
            return Select::make($field)
                ->label(self::label($field))
                ->options(self::optionsFor($field))
                ->searchable()
                ->native(false)
                ->required(self::isRequired($field, $modelClass))
                ->default(self::defaultFor($field));
        }

        if (self::isBoolean($field)) {
            return Toggle::make($field)
                ->label(self::label($field))
                ->default(self::defaultFor($field) ?? false);
        }

        if (self::isDateTime($field)) {
            return self::dateTimeField($field);
        }

        if (self::isDate($field)) {
            $input = DatePicker::make($field)
                ->label(self::label($field))
                ->native(false)
                ->required(self::isRequired($field, $modelClass));

            if (self::dateAfterField($field) !== null) {
                $input = $input->afterOrEqual(self::dateAfterField($field));
            }

            return $input;
        }

        if (in_array($field, ['metadata', 'allowance_summary', 'benefit_summary', 'old_values', 'new_values'], true)) {
            return KeyValue::make($field)
                ->label(self::label($field))
                ->columnSpanFull();
        }

        if (self::isLongText($field)) {
            return Textarea::make($field)
                ->label(self::label($field))
                ->rows(4)
                ->required(self::isRequired($field, $modelClass))
                ->columnSpanFull();
        }

        $input = TextInput::make($field)
            ->label(self::label($field))
            ->required(self::isRequired($field, $modelClass))
            ->maxLength(255)
            ->default(self::defaultFor($field));

        if (self::isNumeric($field)) {
            $input = $input
                ->numeric()
                ->minValue(0)
                ->step(Str::contains($field, ['salary', 'score', 'percent', 'years']) ? '0.0001' : '1');

            if (Str::contains($field, 'percent')) {
                $input = $input->suffix('%')->maxValue(100);
            }
        }

        if (Str::contains($field, ['number', 'code']) && ! Str::contains($field, ['phone', 'notice'])) {
            $input = $input->copyable();
        }

        return $input;
    }

    private static function dateTimeField(string $field): DateTimePicker
    {
        return DateTimePicker::make($field)
            ->label(self::label($field))
            ->native(false)
            ->seconds(false);
    }

    private static function infolistEntry(string $field): object
    {
        if (self::isBoolean($field)) {
            return IconEntry::make($field)->label(self::label($field))->boolean();
        }

        $entry = TextEntry::make($field)
            ->label(self::label($field))
            ->placeholder('—');

        if (self::isForeignKey($field)) {
            $entry = $entry->formatStateUsing(fn (mixed $state): ?string => self::optionLabel($field, $state));
        }

        if (self::hasOptions($field)) {
            $entry = $entry
                ->badge()
                ->formatStateUsing(fn (?string $state): string => self::formatOption($field, $state))
                ->color(fn (?string $state): string => self::statusColor($state));
        }

        if (self::isDate($field)) {
            $entry = $entry->date();
        }

        if (self::isDateTime($field)) {
            $entry = $entry->dateTime();
        }

        if (self::isLongText($field) || in_array($field, ['metadata', 'old_values', 'new_values'], true)) {
            $entry = $entry->columnSpanFull();
        }

        return $entry;
    }

    private static function tableColumn(string $field): object
    {
        if (self::isBoolean($field)) {
            return IconColumn::make($field)->label(self::label($field))->boolean()->sortable();
        }

        $column = TextColumn::make($field)
            ->label(self::label($field))
            ->placeholder('—')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: self::hiddenByDefault($field));

        if (self::isSearchable($field)) {
            $column = $column->searchable();
        }

        if (self::isForeignKey($field)) {
            $column = $column
                ->formatStateUsing(fn (mixed $state): ?string => self::optionLabel($field, $state))
                ->searchable(false);
        }

        if (self::hasOptions($field)) {
            $column = $column
                ->badge()
                ->formatStateUsing(fn (?string $state): string => self::formatOption($field, $state))
                ->color(fn (?string $state): string => self::statusColor($state));
        }

        if (self::isDate($field)) {
            $column = $column->date();
        }

        if (self::isDateTime($field)) {
            $column = $column->dateTime();
        }

        if (self::isNumeric($field)) {
            $column = Str::contains($field, ['salary', 'score', 'percent', 'amount', 'years'])
                ? $column->numeric(decimalPlaces: 2)
                : $column->numeric();
        }

        if (Str::contains($field, ['number', 'code']) && ! Str::contains($field, ['phone', 'notice'])) {
            $column = $column->copyable()->weight('bold');
        }

        if (self::isLongText($field)) {
            $column = $column->wrap()->limit(80);
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, object>
     */
    private static function filters(array $config): array
    {
        return array_map(function (string $field): object {
            if (self::isBoolean($field)) {
                return TernaryFilter::make($field)
                    ->label(self::label($field))
                    ->placeholder('All')
                    ->trueLabel('Yes')
                    ->falseLabel('No');
            }

            return SelectFilter::make($field)
                ->label(self::label($field))
                ->options(fn (): array => self::optionsFor($field))
                ->searchable()
                ->preload()
                ->native(false);
        }, $config['filters'] ?? []);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, object>
     */
    private static function recordActions(string $modelClass): array
    {
        if ((self::config($modelClass)['readOnly'] ?? false) === true) {
            return [ViewAction::make()];
        }

        return [
            ViewAction::make(),
            EditAction::make(),
        ];
    }

    private static function label(string $field): string
    {
        return (string) Str::of($field)
            ->replace([
                'recruitment_application_screening_id',
                'recruitment_interview_scorecard_template_id',
                'recruitment_onboarding_template_id',
                'recruitment_onboarding_plan_id',
                'recruitment_requisition_id',
                'recruitment_application_id',
                'recruitment_candidate_id',
                'recruitment_interview_id',
                'recruitment_vacancy_id',
                '_employee_id',
                '_user_id',
                '_id',
            ], [
                'application_screening',
                'scorecard_template',
                'onboarding_template',
                'onboarding_plan',
                'requisition',
                'application',
                'candidate',
                'interview',
                'vacancy',
                '',
                '',
                '',
            ])
            ->replace('_', ' ')
            ->headline();
    }

    private static function isRequired(string $field, string $modelClass): bool
    {
        $required = [
            'requisition_number', 'vacancy_number', 'application_number', 'candidate_number', 'offer_number',
            'title', 'first_name', 'last_name', 'email', 'department_id', 'employment_type',
            'requested_headcount', 'number_of_openings', 'requisition_type', 'justification',
            'hiring_manager_employee_id', 'recruitment_requisition_id', 'recruitment_vacancy_id',
            'recruitment_candidate_id', 'application_date', 'code', 'name', 'assessment_type',
            'screening_template_id', 'screening_template_version', 'interview_type',
            'recruitment_interview_panel_id', 'round_number', 'scheduled_start', 'scheduled_end',
            'status', 'check_type', 'proposed_start_date', 'valid_until', 'start_date',
            'target_completion_date', 'due_date', 'task_category', 'decision_type',
            'proposed_effective_date',
        ];

        return in_array($field, $required, true);
    }

    private static function isLifecycleField(string $field): bool
    {
        return Str::endsWith($field, '_at')
            && ! in_array($field, ['published_at', 'closes_at', 'scheduled_start', 'scheduled_end', 'requested_at', 'completed_at', 'consent_given_at', 'occurred_at'], true);
    }

    private static function isForeignKey(string $field): bool
    {
        return Str::endsWith($field, '_id') || in_array($field, ['screened_by', 'published_by', 'withdrawn_by', 'created_by', 'reviewed_by', 'approved_by', 'issued_by', 'requested_by', 'completed_by', 'actor_id'], true);
    }

    private static function isBoolean(string $field): bool
    {
        return Str::startsWith($field, ['is_', 'requires_', 'mandatory_'])
            || in_array($field, ['conflict_of_interest_declared'], true);
    }

    private static function isDate(string $field): bool
    {
        return Str::endsWith($field, '_date') || Str::endsWith($field, '_until') || Str::endsWith($field, '_from') || Str::endsWith($field, '_to') || in_array($field, ['opening_date', 'target_closing_date', 'actual_closing_date', 'valid_until', 'due_date', 'expires_at', 'effective_from', 'effective_to'], true);
    }

    private static function isDateTime(string $field): bool
    {
        return Str::endsWith($field, '_at') || in_array($field, ['scheduled_start', 'scheduled_end', 'published_at', 'closes_at', 'occurred_at'], true);
    }

    private static function isLongText(string $field): bool
    {
        return Str::contains($field, ['description', 'summary', 'notes', 'reason', 'justification', 'qualification', 'experience', 'skills', 'responsibilities', 'instructions', 'letter', 'requirements', 'address']);
    }

    private static function isNumeric(string $field): bool
    {
        return Str::contains($field, ['headcount', 'openings', 'salary', 'score', 'percent', 'years', 'version', 'months', 'days', 'duration', 'position_id', 'job_title_id', 'grade_id']);
    }

    private static function isSearchable(string $field): bool
    {
        return in_array($field, ['requisition_number', 'vacancy_number', 'application_number', 'candidate_number', 'offer_number', 'code', 'name', 'title', 'first_name', 'last_name', 'email', 'status', 'current_stage'], true);
    }

    private static function hiddenByDefault(string $field): bool
    {
        return Str::endsWith($field, ['_at', '_by'])
            || in_array($field, ['created_at', 'updated_at', 'description', 'summary', 'notes'], true);
    }

    private static function dateAfterField(string $field): ?string
    {
        return match ($field) {
            'budgeted_salary_max' => 'budgeted_salary_min',
            'salary_max' => 'salary_min',
            'actual_closing_date', 'target_closing_date' => 'opening_date',
            'scheduled_end' => 'scheduled_start',
            'contract_end_date', 'valid_until' => 'proposed_start_date',
            'target_completion_date' => 'start_date',
            'effective_to' => 'effective_from',
            default => null,
        };
    }

    private static function defaultFor(string $field): mixed
    {
        return match ($field) {
            'priority' => 'normal',
            'status' => 'draft',
            'current_stage' => 'applied',
            'source' => 'direct',
            'visibility' => 'internal_and_external',
            'salary_display_type' => 'hidden',
            'channel_type' => 'internal',
            'version', 'offer_version', 'round_number' => 1,
            'number_of_openings', 'requested_headcount' => 1,
            'filled_openings', 'progress_percent' => 0,
            'timezone' => config('app.timezone', 'UTC'),
            'is_active', 'is_required' => true,
            default => null,
        };
    }

    private static function hasOptions(string $field): bool
    {
        return array_key_exists($field, self::staticOptions());
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function staticOptions(): array
    {
        return [
            'status' => ['draft' => 'Draft', 'open' => 'Open', 'active' => 'Active', 'published' => 'Published', 'pending' => 'Pending', 'scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'approved' => 'Approved', 'issued' => 'Issued', 'accepted' => 'Accepted', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'closed' => 'Closed', 'hired' => 'Hired', 'blacklisted' => 'Blacklisted', 'not_started' => 'Not Started', 'waived' => 'Waived'],
            'priority' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'],
            'employment_type' => ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'temporary' => 'Temporary', 'intern' => 'Intern'],
            'requisition_type' => ['new_position' => 'New Position', 'replacement' => 'Replacement', 'backfill' => 'Backfill', 'temporary' => 'Temporary'],
            'visibility' => ['internal' => 'Internal', 'external' => 'External', 'internal_and_external' => 'Internal and External'],
            'source' => ['direct' => 'Direct', 'referral' => 'Referral', 'agency' => 'Agency', 'job_board' => 'Job Board', 'internal' => 'Internal', 'social_media' => 'Social Media'],
            'current_stage' => ['applied' => 'Applied', 'screening' => 'Screening', 'assessment' => 'Assessment', 'interview' => 'Interview', 'selection' => 'Selection', 'offer' => 'Offer', 'onboarding' => 'Onboarding', 'hired' => 'Hired', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'],
            'channel_type' => ['internal' => 'Internal', 'career_site' => 'Career Site', 'job_board' => 'Job Board', 'agency' => 'Agency', 'social_media' => 'Social Media'],
            'salary_display_type' => ['hidden' => 'Hidden', 'range' => 'Range', 'exact' => 'Exact'],
            'assessment_type' => ['technical' => 'Technical', 'aptitude' => 'Aptitude', 'case_study' => 'Case Study', 'presentation' => 'Presentation', 'other' => 'Other'],
            'interview_type' => ['phone' => 'Phone', 'video' => 'Video', 'onsite' => 'On-site', 'panel' => 'Panel', 'technical' => 'Technical', 'final' => 'Final'],
            'recommendation' => ['advance' => 'Advance', 'hold' => 'Hold', 'reject' => 'Reject', 'hire' => 'Hire', 'no_hire' => 'Do Not Hire'],
            'override_recommendation' => ['advance' => 'Advance', 'hold' => 'Hold', 'reject' => 'Reject'],
            'check_type' => ['background' => 'Background', 'reference' => 'Reference', 'identity' => 'Identity', 'medical' => 'Medical', 'education' => 'Education', 'employment' => 'Employment'],
            'task_category' => ['documentation' => 'Documentation', 'equipment' => 'Equipment', 'training' => 'Training', 'orientation' => 'Orientation', 'compliance' => 'Compliance', 'other' => 'Other'],
            'applicable_employment_type' => ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'temporary' => 'Temporary', 'intern' => 'Intern'],
            'pay_frequency' => ['monthly' => 'Monthly', 'weekly' => 'Weekly', 'biweekly' => 'Biweekly'],
            'decision_type' => ['confirm' => 'Confirm Employment', 'extend_probation' => 'Extend Probation', 'terminate' => 'Terminate', 'defer' => 'Defer Decision'],
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private static function optionsFor(string $field): array
    {
        if (self::hasOptions($field)) {
            return self::staticOptions()[$field];
        }

        if (isset(self::$optionCache[$field])) {
            return self::$optionCache[$field];
        }

        return self::$optionCache[$field] = match ($field) {
            'business_id' => self::pluck(Business::class, 'name'),
            'department_id', 'applicable_department_id' => self::pluck(Department::class, 'name'),
            'work_center_id' => self::pluck(WorkCenter::class, 'name'),
            'location_id' => self::pluck(Location::class, 'name'),
            'hiring_manager_employee_id', 'recruiter_employee_id', 'replacement_for_employee_id', 'referred_by_employee_id', 'internal_candidate_employee_id', 'assigned_recruiter_employee_id', 'hired_employee_id', 'chair_employee_id', 'reporting_manager_employee_id', 'employee_id', 'assigned_hr_employee_id', 'manager_employee_id', 'assigned_employee_id', 'duplicate_of_candidate_id' => self::employeeOptions(),
            'candidate_user_id', 'submitted_by', 'approved_by', 'rejected_by', 'cancelled_by', 'screened_by', 'published_by', 'withdrawn_by', 'created_by', 'reviewed_by', 'issued_by', 'requested_by', 'completed_by', 'assigned_user_id', 'actor_id', 'implemented_by' => self::pluck(User::class, 'name'),
            'recruitment_requisition_id' => self::numberedOptions(RecruitmentRequisition::class, 'requisition_number', 'title'),
            'recruitment_vacancy_id' => self::numberedOptions(RecruitmentVacancy::class, 'vacancy_number', 'title'),
            'source_posting_id' => self::numberedOptions(RecruitmentJobPosting::class, 'channel_type', 'channel_name'),
            'recruitment_candidate_id' => self::candidateOptions(),
            'recruitment_application_id' => self::numberedOptions(RecruitmentApplication::class, 'application_number', 'status'),
            'rejection_reason_id' => self::pluck(RecruitmentRejectionReason::class, 'name'),
            'screening_template_id' => self::numberedOptions(RecruitmentScreeningTemplate::class, 'code', 'name'),
            'recruitment_assessment_id' => self::numberedOptions(RecruitmentAssessment::class, 'code', 'title'),
            'recruitment_interview_panel_id' => self::pluck(RecruitmentInterviewPanel::class, 'name'),
            'scorecard_template_id' => self::numberedOptions(RecruitmentInterviewScorecardTemplate::class, 'code', 'name'),
            'recruitment_offer_id' => self::numberedOptions(RecruitmentOffer::class, 'offer_number', 'status'),
            'onboarding_template_id' => self::numberedOptions(RecruitmentOnboardingTemplate::class, 'code', 'name'),
            'recruitment_onboarding_plan_id' => self::numberedOptions(RecruitmentOnboardingPlan::class, 'id', 'status'),
            'source_template_task_id' => self::numberedOptions(RecruitmentOnboardingTemplateTask::class, 'code', 'title'),
            'performance_probation_review_id' => self::numberedOptions(PerformanceProbationReview::class, 'review_date', 'status'),
            default => [],
        };
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int|string, string>
     */
    private static function pluck(string $modelClass, string $labelColumn): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;

        if (! DB::getSchemaBuilder()->hasTable($model->getTable()) || ! DB::getSchemaBuilder()->hasColumn($model->getTable(), $labelColumn)) {
            return [];
        }

        return $modelClass::query()
            ->orderBy($labelColumn)
            ->limit(500)
            ->pluck($labelColumn, 'id')
            ->all();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int|string, string>
     */
    private static function numberedOptions(string $modelClass, string $numberColumn, string $labelColumn): array
    {
        /** @var Model $model */
        $model = new $modelClass;

        if (
            ! DB::getSchemaBuilder()->hasTable($model->getTable())
            || ! DB::getSchemaBuilder()->hasColumn($model->getTable(), $numberColumn)
            || ! DB::getSchemaBuilder()->hasColumn($model->getTable(), $labelColumn)
        ) {
            return [];
        }

        return $modelClass::query()
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Model $record): array => [
                $record->getKey() => trim((string) $record->getAttribute($numberColumn).' - '.(string) $record->getAttribute($labelColumn), ' -'),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private static function employeeOptions(): array
    {
        return Employee::query()
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'employee_number', 'full_name'])
            ->mapWithKeys(fn (Employee $employee): array => [
                $employee->id => trim($employee->employee_number.' - '.$employee->full_name, ' -'),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private static function candidateOptions(): array
    {
        return RecruitmentCandidate::query()
            ->orderBy('last_name')
            ->limit(500)
            ->get(['id', 'candidate_number', 'first_name', 'last_name'])
            ->mapWithKeys(fn (RecruitmentCandidate $candidate): array => [
                $candidate->id => trim($candidate->candidate_number.' - '.$candidate->first_name.' '.$candidate->last_name, ' -'),
            ])
            ->all();
    }

    private static function optionLabel(string $field, mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return Arr::get(self::optionsFor($field), $state, '#'.$state);
    }

    private static function formatOption(string $field, ?string $state): string
    {
        if (blank($state)) {
            return 'Not Set';
        }

        return self::staticOptions()[$field][$state] ?? Str::headline($state);
    }

    private static function statusColor(?string $state): string
    {
        return match ($state) {
            'active', 'open', 'published', 'approved', 'issued', 'accepted', 'completed', 'hired', 'advance', 'hire' => 'success',
            'draft', 'pending', 'scheduled', 'in_progress', 'hold' => 'warning',
            'rejected', 'declined', 'withdrawn', 'cancelled', 'blacklisted', 'no_hire' => 'danger',
            'closed', 'waived' => 'gray',
            default => 'info',
        };
    }
}
