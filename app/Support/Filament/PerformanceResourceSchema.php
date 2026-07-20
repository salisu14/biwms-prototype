<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Business;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Manufacturing\WorkCenter;
use App\Models\PerformanceAppraisal;
use App\Models\PerformanceAppraisalCycle;
use App\Models\PerformanceAppraisalCycleAssignment;
use App\Models\PerformanceAppraisalDispute;
use App\Models\PerformanceAppraisalHistory;
use App\Models\PerformanceAppraisalModerationSession;
use App\Models\PerformanceAppraisalRecommendation;
use App\Models\PerformanceAppraisalTemplate;
use App\Models\PerformanceCompetency;
use App\Models\PerformanceCompetencyFramework;
use App\Models\PerformanceDevelopmentPlan;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalPlan;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformancePositionCompetency;
use App\Models\PerformanceProbationReview;
use App\Models\PerformanceRatingScale;
use App\Models\PerformanceRatingScaleLevel;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceResourceSchema
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
                    $section['fields']
                )),
            $config['sections']
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
                            $section['fields']
                        )),
                    $config['sections']
                )),
        ]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function table(Table $table, string $modelClass): Table
    {
        $config = self::config($modelClass);

        $table = $table
            ->columns(array_map(
                fn (string $field): object => self::tableColumn($field),
                $config['table']
            ))
            ->filters(
                self::filters($config),
                layout: ($config['filtersLayout'] ?? true) === true
                    ? FiltersLayout::AboveContentCollapsible
                    : null,
            )
            ->defaultSort($config['defaultSort'] ?? 'updated_at', $config['defaultSortDirection'] ?? 'desc')
            ->recordActions(self::recordActions($modelClass))
            ->toolbarActions(self::toolbarActions($modelClass))
            ->emptyStateHeading('No '.Str::headline(class_basename($modelClass)).' records')
            ->emptyStateDescription('Create or import records when the performance process reaches this step.');

        if (($config['filtersLayout'] ?? true) === true) {
            $table = $table->filtersFormColumns(4);
        }

        return $table;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, mixed>
     */
    private static function config(string $modelClass): array
    {
        return match ($modelClass) {
            PerformanceRatingScale::class => [
                'sections' => [
                    ['label' => 'Scale Information', 'icon' => 'heroicon-o-scale', 'fields' => ['business_id', 'code', 'name', 'description']],
                    ['label' => 'Score Range', 'icon' => 'heroicon-o-adjustments-horizontal', 'fields' => ['minimum_score', 'maximum_score', 'decimal_places']],
                    ['label' => 'Validity and Status', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['effective_from', 'effective_to', 'is_default', 'is_active']],
                ],
                'table' => ['code', 'name', 'business_id', 'minimum_score', 'maximum_score', 'is_default', 'is_active', 'effective_from', 'effective_to', 'updated_at'],
                'filters' => ['business_id', 'is_default', 'is_active'],
                'defaultSort' => 'name',
                'defaultSortDirection' => 'asc',
            ],
            PerformanceAppraisalCycle::class => [
                'sections' => [
                    ['label' => 'Cycle Information', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['business_id', 'code', 'name', 'cycle_type', 'status', 'rating_scale_id', 'description']],
                    ['label' => 'Review Period', 'icon' => 'heroicon-o-clock', 'fields' => ['period_start', 'period_end', 'goal_setting_start', 'goal_setting_end', 'self_assessment_start', 'self_assessment_end', 'manager_review_start', 'manager_review_end', 'moderation_start', 'moderation_end', 'acknowledgement_deadline']],
                    ['label' => 'Workflow Controls', 'icon' => 'heroicon-o-lock-closed', 'fields' => ['allow_self_assessment', 'allow_peer_review', 'allow_secondary_reviewer', 'require_employee_acknowledgement', 'require_moderation', 'lock_completed_reviews']],
                    ['label' => 'Lifecycle', 'icon' => 'heroicon-o-shield-check', 'fields' => ['opened_at', 'completed_at', 'closed_at', 'reopened_at', 'reopen_reason']],
                ],
                'table' => ['code', 'name', 'business_id', 'cycle_type', 'status', 'period_start', 'period_end', 'rating_scale_id', 'updated_at'],
                'filters' => ['business_id', 'cycle_type', 'status', 'rating_scale_id'],
                'defaultSort' => 'period_start',
            ],
            PerformanceAppraisalTemplate::class => [
                'sections' => [
                    ['label' => 'Template Information', 'icon' => 'heroicon-o-document-text', 'fields' => ['business_id', 'code', 'name', 'version', 'rating_scale_id', 'description']],
                    ['label' => 'Applicability', 'icon' => 'heroicon-o-user-group', 'fields' => ['applicable_department_id', 'applicable_employment_type', 'applicable_position_id', 'applicable_grade_id']],
                    ['label' => 'Scoring Weights', 'icon' => 'heroicon-o-calculator', 'fields' => ['goal_weight_percent', 'competency_weight_percent', 'other_weight_percent']],
                    ['label' => 'Review Controls', 'icon' => 'heroicon-o-clipboard-document-check', 'fields' => ['require_self_comment', 'require_manager_comment', 'require_final_comment', 'allow_not_applicable', 'is_active', 'effective_from', 'effective_to']],
                ],
                'table' => ['code', 'name', 'business_id', 'rating_scale_id', 'applicable_department_id', 'version', 'is_active', 'effective_from', 'effective_to'],
                'filters' => ['business_id', 'rating_scale_id', 'applicable_department_id', 'is_active'],
                'defaultSort' => 'name',
                'defaultSortDirection' => 'asc',
            ],
            PerformanceCompetencyFramework::class => [
                'sections' => [
                    ['label' => 'Framework Information', 'icon' => 'heroicon-o-squares-2x2', 'fields' => ['business_id', 'code', 'name', 'description']],
                    ['label' => 'Effectiveness and Status', 'icon' => 'heroicon-o-calendar', 'fields' => ['effective_from', 'effective_to', 'is_active']],
                ],
                'table' => ['code', 'name', 'business_id', 'is_active', 'effective_from', 'effective_to', 'updated_at'],
                'filters' => ['business_id', 'is_active'],
                'defaultSort' => 'name',
                'defaultSortDirection' => 'asc',
            ],
            PerformanceCompetency::class => [
                'sections' => [
                    ['label' => 'Competency Details', 'icon' => 'heroicon-o-academic-cap', 'fields' => ['performance_competency_framework_id', 'parent_competency_id', 'code', 'name', 'competency_type', 'description']],
                    ['label' => 'Ordering and Status', 'icon' => 'heroicon-o-bars-arrow-down', 'fields' => ['sort_order', 'is_active']],
                ],
                'table' => ['code', 'name', 'performance_competency_framework_id', 'parent_competency_id', 'competency_type', 'is_active', 'sort_order', 'updated_at'],
                'filters' => ['performance_competency_framework_id', 'competency_type', 'is_active'],
                'defaultSort' => 'sort_order',
                'defaultSortDirection' => 'asc',
            ],
            PerformancePositionCompetency::class => [
                'sections' => [
                    ['label' => 'Position Scope', 'icon' => 'heroicon-o-briefcase', 'fields' => ['department_id', 'position_id', 'job_title_id', 'grade_id']],
                    ['label' => 'Competency Requirement', 'icon' => 'heroicon-o-academic-cap', 'fields' => ['performance_competency_id', 'expected_level', 'weight_percent', 'is_required']],
                    ['label' => 'Effective Period', 'icon' => 'heroicon-o-calendar', 'fields' => ['effective_from', 'effective_to']],
                ],
                'table' => ['department_id', 'performance_competency_id', 'expected_level', 'weight_percent', 'is_required', 'effective_from', 'effective_to'],
                'filters' => ['department_id', 'performance_competency_id', 'is_required'],
                'defaultSort' => 'effective_from',
            ],
            PerformanceGoalPlan::class => [
                'sections' => [
                    ['label' => 'Employee and Cycle', 'icon' => 'heroicon-o-user-circle', 'fields' => ['performance_appraisal_cycle_id', 'employee_id', 'manager_employee_id', 'status']],
                    ['label' => 'Goal Plan Review', 'icon' => 'heroicon-o-chart-bar', 'fields' => ['total_weight_percent', 'submitted_at', 'approved_at', 'revision_requested_at', 'revision_reason']],
                ],
                'table' => ['employee_id', 'performance_appraisal_cycle_id', 'manager_employee_id', 'status', 'total_weight_percent', 'submitted_at', 'approved_at', 'updated_at'],
                'filters' => ['performance_appraisal_cycle_id', 'employee_id', 'manager_employee_id', 'status'],
            ],
            PerformanceGoal::class => [
                'sections' => [
                    ['label' => 'Goal Details', 'icon' => 'heroicon-o-flag', 'fields' => ['performance_goal_plan_id', 'employee_id', 'code', 'title', 'goal_type', 'status', 'description']],
                    ['label' => 'Measurement', 'icon' => 'heroicon-o-chart-pie', 'fields' => ['measurement_type', 'baseline_value', 'target_value', 'current_value', 'unit', 'scoring_direction', 'weight_percent', 'progress_percent']],
                    ['label' => 'Timeline and Comments', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['start_date', 'due_date', 'completed_at', 'employee_comment', 'manager_comment']],
                ],
                'table' => ['title', 'employee_id', 'performance_goal_plan_id', 'goal_type', 'status', 'progress_percent', 'weight_percent', 'due_date', 'updated_at'],
                'filters' => ['employee_id', 'performance_goal_plan_id', 'goal_type', 'status'],
                'defaultSort' => 'due_date',
            ],
            PerformanceAppraisal::class => [
                'sections' => [
                    ['label' => 'Employee and Appraisal Context', 'icon' => 'heroicon-o-clipboard-document-list', 'fields' => ['performance_appraisal_cycle_assignment_id', 'performance_appraisal_cycle_id', 'employee_id', 'manager_employee_id', 'secondary_reviewer_employee_id', 'appraisal_template_id', 'appraisal_template_version', 'rating_scale_id', 'status']],
                    ['label' => 'Scoring Summary', 'icon' => 'heroicon-o-calculator', 'fields' => ['goal_score', 'competency_score', 'other_score', 'calculated_score', 'moderated_score', 'final_score', 'final_rating_level_id']],
                    ['label' => 'Review Comments', 'icon' => 'heroicon-o-chat-bubble-left-right', 'fields' => ['employee_overall_comment', 'manager_overall_comment', 'moderator_comment', 'final_comment']],
                    ['label' => 'Workflow Timestamps', 'icon' => 'heroicon-o-clock', 'fields' => ['self_submitted_at', 'manager_submitted_at', 'moderated_at', 'finalized_at', 'acknowledged_at']],
                ],
                'table' => ['employee_id', 'performance_appraisal_cycle_id', 'manager_employee_id', 'status', 'calculated_score', 'moderated_score', 'final_score', 'final_rating_level_id', 'updated_at'],
                'filters' => ['performance_appraisal_cycle_id', 'employee_id', 'manager_employee_id', 'status', 'final_rating_level_id'],
            ],
            PerformanceAppraisalModerationSession::class => [
                'sections' => [
                    ['label' => 'Moderation Session', 'icon' => 'heroicon-o-scale', 'fields' => ['performance_appraisal_cycle_id', 'code', 'name', 'scope_type', 'status']],
                    ['label' => 'Scope and Schedule', 'icon' => 'heroicon-o-building-office-2', 'fields' => ['department_id', 'work_center_id', 'scheduled_at', 'completed_at', 'created_by']],
                ],
                'table' => ['code', 'name', 'performance_appraisal_cycle_id', 'scope_type', 'department_id', 'status', 'scheduled_at', 'completed_at'],
                'filters' => ['performance_appraisal_cycle_id', 'scope_type', 'department_id', 'status'],
            ],
            PerformanceAppraisalDispute::class => [
                'sections' => [
                    ['label' => 'Dispute Details', 'icon' => 'heroicon-o-exclamation-triangle', 'fields' => ['performance_appraisal_id', 'employee_id', 'dispute_type', 'status', 'reason', 'requested_resolution']],
                    ['label' => 'Assignment and Resolution', 'icon' => 'heroicon-o-check-badge', 'fields' => ['assigned_to', 'submitted_at', 'resolved_by', 'resolved_at', 'resolution_summary']],
                ],
                'table' => ['employee_id', 'performance_appraisal_id', 'dispute_type', 'status', 'assigned_to', 'submitted_at', 'resolved_at'],
                'filters' => ['employee_id', 'dispute_type', 'status', 'assigned_to'],
            ],
            PerformanceDevelopmentPlan::class => [
                'sections' => [
                    ['label' => 'Development Plan', 'icon' => 'heroicon-o-sparkles', 'fields' => ['employee_id', 'performance_appraisal_id', 'performance_appraisal_cycle_id', 'manager_employee_id', 'status']],
                    ['label' => 'Objectives and Period', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['start_date', 'target_completion_date', 'completed_at', 'overall_objective']],
                    ['label' => 'Comments and Approval', 'icon' => 'heroicon-o-chat-bubble-left-right', 'fields' => ['employee_comment', 'manager_comment', 'approved_at']],
                ],
                'table' => ['employee_id', 'performance_appraisal_cycle_id', 'manager_employee_id', 'status', 'start_date', 'target_completion_date', 'completed_at'],
                'filters' => ['employee_id', 'performance_appraisal_cycle_id', 'manager_employee_id', 'status'],
                'defaultSort' => 'target_completion_date',
            ],
            PerformanceImprovementPlan::class => [
                'sections' => [
                    ['label' => 'Improvement Plan', 'icon' => 'heroicon-o-arrow-trending-up', 'fields' => ['employee_id', 'performance_appraisal_id', 'manager_employee_id', 'initiated_by', 'hr_reviewer_id', 'status']],
                    ['label' => 'Plan Period and Expectations', 'icon' => 'heroicon-o-calendar', 'fields' => ['start_date', 'end_date', 'review_frequency', 'reason_summary', 'expectations_summary', 'support_summary']],
                    ['label' => 'Outcome and Approval', 'icon' => 'heroicon-o-check-circle', 'fields' => ['employee_acknowledged_at', 'approved_at', 'completed_at', 'outcome_summary']],
                ],
                'table' => ['employee_id', 'manager_employee_id', 'status', 'start_date', 'end_date', 'review_frequency', 'approved_at', 'completed_at'],
                'filters' => ['employee_id', 'manager_employee_id', 'status', 'hr_reviewer_id'],
                'defaultSort' => 'end_date',
            ],
            PerformanceProbationReview::class => [
                'sections' => [
                    ['label' => 'Probation Review', 'icon' => 'heroicon-o-check-badge', 'fields' => ['employee_id', 'manager_employee_id', 'review_type', 'status', 'performance_score']],
                    ['label' => 'Probation Dates', 'icon' => 'heroicon-o-calendar-days', 'fields' => ['probation_start_date', 'expected_confirmation_date', 'review_date', 'recommended_extension_end_date']],
                    ['label' => 'Assessment and Decision', 'icon' => 'heroicon-o-document-check', 'fields' => ['strengths', 'improvement_areas', 'manager_recommendation', 'hr_decision', 'decision_reason', 'approved_at']],
                ],
                'table' => ['employee_id', 'manager_employee_id', 'review_type', 'status', 'performance_score', 'review_date', 'manager_recommendation', 'hr_decision'],
                'filters' => ['employee_id', 'manager_employee_id', 'review_type', 'status', 'manager_recommendation', 'hr_decision'],
                'defaultSort' => 'review_date',
            ],
            PerformanceAppraisalRecommendation::class => [
                'sections' => [
                    ['label' => 'Recommendation', 'icon' => 'heroicon-o-light-bulb', 'fields' => ['performance_appraisal_id', 'recommendation_type', 'status', 'recommendation_text']],
                    ['label' => 'Review and Implementation', 'icon' => 'heroicon-o-check-badge', 'fields' => ['recommended_by', 'reviewed_by', 'reviewed_at', 'implementation_reference_type', 'implementation_reference_id']],
                ],
                'table' => ['performance_appraisal_id', 'recommendation_type', 'status', 'recommended_by', 'reviewed_by', 'reviewed_at', 'updated_at'],
                'filters' => ['recommendation_type', 'status', 'recommended_by', 'reviewed_by'],
            ],
            PerformanceAppraisalHistory::class => [
                'sections' => [
                    ['label' => 'History Event', 'icon' => 'heroicon-o-clock', 'fields' => ['performance_appraisal_id', 'performance_appraisal_cycle_id', 'employee_id', 'event_type', 'changed_by', 'changed_at', 'reason']],
                    ['label' => 'Recorded Values', 'icon' => 'heroicon-o-code-bracket-square', 'fields' => ['before_values', 'after_values']],
                ],
                'table' => ['event_type', 'employee_id', 'performance_appraisal_id', 'performance_appraisal_cycle_id', 'changed_by', 'changed_at', 'created_at'],
                'filters' => ['event_type', 'employee_id', 'performance_appraisal_cycle_id', 'changed_by'],
                'defaultSort' => 'changed_at',
                'readOnly' => true,
            ],
            default => throw new \InvalidArgumentException('Unsupported performance resource schema: '.$modelClass),
        };
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function formField(string $field, string $modelClass): object
    {
        if (self::isRequiredActorField($field)) {
            return Hidden::make($field)->default(fn (): ?int => auth()->id());
        }

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

        if (in_array($field, ['before_values', 'after_values', 'template_snapshot', 'rating_scale_snapshot', 'calculation_snapshot', 'attendance_context'], true)) {
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
            ->maxLength(self::maxLength($field))
            ->default(self::defaultFor($field));

        if (self::isNumeric($field)) {
            $input = $input
                ->numeric()
                ->step(self::numericStep($field))
                ->minValue(self::minValue($field));

            if (self::maxValue($field) !== null) {
                $input = $input->maxValue(self::maxValue($field));
            }

            if (Str::contains($field, 'percent')) {
                $input = $input->suffix('%');
            }
        }

        if (in_array($field, ['code', 'amendment_number'], true)) {
            $input = $input->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::upper(Str::slug($state, '_')) : null);
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
            return IconEntry::make($field)
                ->label(self::label($field))
                ->boolean();
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

        if (self::isLongText($field) || in_array($field, ['before_values', 'after_values'], true)) {
            $entry = $entry->columnSpanFull();
        }

        return $entry;
    }

    private static function tableColumn(string $field): object
    {
        if (self::isBoolean($field)) {
            return IconColumn::make($field)
                ->label(self::label($field))
                ->boolean()
                ->sortable();
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
            $column = Str::contains($field, ['percent', 'score', 'value'])
                ? $column->numeric(decimalPlaces: 2)
                : $column->numeric();
        }

        if (in_array($field, ['code', 'amendment_number'], true)) {
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

            if (self::hasOptions($field) || self::isForeignKey($field)) {
                return SelectFilter::make($field)
                    ->label(self::label($field))
                    ->options(fn (): array => self::optionsFor($field))
                    ->searchable()
                    ->preload()
                    ->native(false);
            }

            return Filter::make($field)
                ->label(self::label($field));
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
            DeleteAction::make(),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, object>
     */
    private static function toolbarActions(string $modelClass): array
    {
        if ((self::config($modelClass)['readOnly'] ?? false) === true) {
            return [];
        }

        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
            ]),
        ];
    }

    private static function label(string $field): string
    {
        return (string) Str::of($field)
            ->replace([
                'performance_appraisal_cycle_id',
                'performance_appraisal_cycle_assignment_id',
                'performance_appraisal_template_id',
                'performance_competency_framework_id',
                'performance_goal_plan_id',
                'performance_appraisal_id',
                'performance_competency_id',
                '_id',
            ], [
                'appraisal_cycle',
                'cycle_assignment',
                'appraisal_template',
                'competency_framework',
                'goal_plan',
                'appraisal',
                'competency',
                '',
            ])
            ->replace('_', ' ')
            ->headline();
    }

    private static function isRequired(string $field, string $modelClass): bool
    {
        $required = [
            'code',
            'name',
            'title',
            'minimum_score',
            'maximum_score',
            'decimal_places',
            'period_start',
            'period_end',
            'rating_scale_id',
            'appraisal_template_id',
            'appraisal_template_version',
            'performance_appraisal_cycle_id',
            'performance_appraisal_cycle_assignment_id',
            'performance_appraisal_template_section_id',
            'performance_competency_framework_id',
            'performance_competency_id',
            'performance_goal_plan_id',
            'employee_id',
            'performance_appraisal_id',
            'reason',
            'recommendation_type',
            'recommendation_text',
            'overall_objective',
            'start_date',
            'end_date',
            'target_completion_date',
            'probation_start_date',
            'expected_confirmation_date',
            'review_date',
            'manager_recommendation',
            'expected_level',
            'effective_from',
            'issue_date',
            'changed_at',
        ];

        if (in_array($field, $required, true)) {
            return true;
        }

        return match ($modelClass) {
            PerformanceAppraisalModerationSession::class => in_array($field, ['created_by'], true),
            PerformanceImprovementPlan::class => in_array($field, ['initiated_by', 'reason_summary', 'expectations_summary'], true),
            PerformanceAppraisalRecommendation::class => in_array($field, ['recommended_by'], true),
            default => false,
        };
    }

    private static function isRequiredActorField(string $field): bool
    {
        return in_array($field, ['created_by', 'submitted_by', 'initiated_by', 'reviewed_by', 'recommended_by', 'requested_by', 'changed_by'], true);
    }

    private static function isLifecycleField(string $field): bool
    {
        return Str::endsWith($field, ['_at']) && ! in_array($field, ['scheduled_at', 'changed_at'], true);
    }

    private static function isForeignKey(string $field): bool
    {
        return Str::endsWith($field, '_id') || in_array($field, ['assigned_to', 'resolved_by', 'created_by', 'submitted_by', 'initiated_by', 'hr_reviewer_id', 'approved_by', 'completed_by', 'recommended_by', 'reviewed_by', 'requested_by', 'changed_by', 'opened_by', 'closed_by', 'reopened_by'], true);
    }

    private static function isBoolean(string $field): bool
    {
        return Str::startsWith($field, ['is_', 'allow_', 'require_', 'lock_']);
    }

    private static function isDate(string $field): bool
    {
        return Str::endsWith($field, '_date') || Str::endsWith($field, '_from') || Str::endsWith($field, '_to') || in_array($field, ['period_start', 'period_end', 'effective_from', 'effective_to', 'due_date'], true);
    }

    private static function isDateTime(string $field): bool
    {
        return Str::endsWith($field, '_at');
    }

    private static function isLongText(string $field): bool
    {
        return Str::contains($field, ['description', 'comment', 'reason', 'summary', 'objective', 'expectation', 'support', 'resolution', 'text', 'strengths', 'areas']);
    }

    private static function isNumeric(string $field): bool
    {
        return Str::contains($field, ['score', 'percent', 'value', 'level', 'order', 'version', 'position_id', 'job_title_id', 'grade_id', 'reference_id', 'decimal_places']);
    }

    private static function isSearchable(string $field): bool
    {
        return in_array($field, ['code', 'name', 'title', 'description', 'status', 'cycle_type', 'competency_type', 'dispute_type', 'recommendation_type'], true);
    }

    private static function hiddenByDefault(string $field): bool
    {
        return in_array($field, ['id', 'created_at', 'updated_at', 'description', 'effective_to', 'opened_at', 'completed_at', 'closed_at', 'reopened_at'], true)
            || Str::endsWith($field, ['_by']);
    }

    private static function maxLength(string $field): int
    {
        return self::isLongText($field) ? 2000 : 255;
    }

    private static function numericStep(string $field): string
    {
        return Str::contains($field, ['score', 'percent', 'value']) ? '0.0001' : '1';
    }

    private static function minValue(string $field): int|float
    {
        return Str::contains($field, ['percent', 'score', 'level', 'order', 'version']) ? 0 : 0;
    }

    private static function maxValue(string $field): int|float|null
    {
        return Str::contains($field, ['percent', 'progress']) ? 100 : null;
    }

    private static function dateAfterField(string $field): ?string
    {
        return match ($field) {
            'period_end' => 'period_start',
            'goal_setting_end' => 'goal_setting_start',
            'self_assessment_end' => 'self_assessment_start',
            'manager_review_end' => 'manager_review_start',
            'moderation_end' => 'moderation_start',
            'effective_to' => 'effective_from',
            'due_date' => 'start_date',
            'target_completion_date' => 'start_date',
            'end_date' => 'start_date',
            'expected_confirmation_date' => 'probation_start_date',
            'review_date' => 'probation_start_date',
            'recommended_extension_end_date' => 'review_date',
            default => null,
        };
    }

    private static function defaultFor(string $field): mixed
    {
        return match ($field) {
            'decimal_places' => 2,
            'minimum_score' => 0,
            'maximum_score' => 100,
            'is_active', 'allow_self_assessment', 'require_employee_acknowledgement', 'lock_completed_reviews', 'is_required', 'allow_not_applicable' => true,
            'goal_weight_percent' => 50,
            'competency_weight_percent' => 40,
            'other_weight_percent' => 10,
            'version' => 1,
            'sort_order', 'progress_percent', 'total_weight_percent' => 0,
            'cycle_type' => 'annual',
            'status' => 'draft',
            'scope_type' => 'business',
            'goal_type' => 'individual',
            'measurement_type' => 'numeric',
            'scoring_direction' => 'higher_is_better',
            'review_type' => 'interim',
            'manager_recommendation' => 'no_recommendation',
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
            'cycle_type' => ['annual' => 'Annual', 'semi_annual' => 'Semi Annual', 'quarterly' => 'Quarterly', 'probation' => 'Probation', 'project' => 'Project'],
            'status' => ['draft' => 'Draft', 'open' => 'Open', 'active' => 'Active', 'employee_submitted' => 'Employee Submitted', 'submitted' => 'Submitted', 'manager_review' => 'Manager Review', 'moderation' => 'Moderation', 'approved' => 'Approved', 'finalized' => 'Finalized', 'completed' => 'Completed', 'closed' => 'Closed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected', 'pending' => 'Pending', 'resolved' => 'Resolved', 'proposed' => 'Proposed', 'successfully_completed' => 'Successfully Completed', 'unsuccessfully_completed' => 'Unsuccessfully Completed'],
            'eligibility_status' => ['eligible' => 'Eligible', 'excluded' => 'Excluded', 'pending' => 'Pending'],
            'section_type' => ['goal' => 'Goal', 'competency' => 'Competency', 'other' => 'Other', 'custom' => 'Custom'],
            'competency_type' => ['technical' => 'Technical', 'behavioral' => 'Behavioral', 'leadership' => 'Leadership', 'communication' => 'Communication', 'analytical' => 'Analytical', 'custom' => 'Custom'],
            'goal_type' => ['individual' => 'Individual', 'team' => 'Team', 'department' => 'Department', 'strategic' => 'Strategic'],
            'measurement_type' => ['numeric' => 'Numeric', 'rating' => 'Rating', 'milestone' => 'Milestone', 'boolean' => 'Yes / No', 'manual' => 'Manual'],
            'scoring_direction' => ['higher_is_better' => 'Higher Is Better', 'lower_is_better' => 'Lower Is Better', 'target' => 'Target Based', 'manual' => 'Manual'],
            'verification_status' => ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'],
            'scope_type' => ['business' => 'Business', 'department' => 'Department', 'work_center' => 'Work Center'],
            'dispute_type' => ['score' => 'Score', 'process' => 'Process', 'comment' => 'Comment', 'other' => 'Other'],
            'action_type' => ['training' => 'Training', 'coaching' => 'Coaching', 'mentoring' => 'Mentoring', 'assignment' => 'Assignment', 'other' => 'Other'],
            'review_type' => ['interim' => 'Interim', 'final' => 'Final', 'extension' => 'Extension'],
            'manager_recommendation' => ['confirm' => 'Confirm', 'extend' => 'Extend', 'terminate' => 'Terminate', 'no_recommendation' => 'No Recommendation'],
            'hr_decision' => ['confirmed' => 'Confirmed', 'extended' => 'Extended', 'terminated' => 'Terminated', 'pending' => 'Pending'],
            'recommendation_type' => ['promotion' => 'Promotion', 'salary_review' => 'Salary Review', 'training' => 'Training', 'transfer' => 'Transfer', 'recognition' => 'Recognition', 'other' => 'Other'],
            'applicable_employment_type' => ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'temporary' => 'Temporary', 'intern' => 'Intern'],
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
            'employee_id', 'manager_employee_id', 'secondary_reviewer_employee_id' => self::pluck(Employee::class, 'full_name'),
            'department_id', 'applicable_department_id' => self::pluck(Department::class, 'name'),
            'rating_scale_id' => self::pluck(PerformanceRatingScale::class, 'name'),
            'final_rating_level_id', 'original_rating_level_id', 'moderated_rating_level_id' => self::pluck(PerformanceRatingScaleLevel::class, 'name'),
            'performance_appraisal_cycle_id' => self::pluck(PerformanceAppraisalCycle::class, 'name'),
            'appraisal_template_id' => self::pluck(PerformanceAppraisalTemplate::class, 'name'),
            'performance_appraisal_cycle_assignment_id' => self::assignmentOptions(),
            'performance_competency_framework_id' => self::pluck(PerformanceCompetencyFramework::class, 'name'),
            'parent_competency_id', 'performance_competency_id', 'competency_id' => self::pluck(PerformanceCompetency::class, 'name'),
            'performance_goal_plan_id' => self::goalPlanOptions(),
            'performance_goal_id' => self::pluck(PerformanceGoal::class, 'title'),
            'performance_appraisal_id' => self::appraisalOptions(),
            'work_center_id' => self::pluck(WorkCenter::class, 'name'),
            'assigned_to', 'resolved_by', 'created_by', 'submitted_by', 'initiated_by', 'hr_reviewer_id', 'approved_by', 'completed_by', 'recommended_by', 'reviewed_by', 'requested_by', 'changed_by', 'opened_by', 'closed_by', 'reopened_by' => self::pluck(User::class, 'name'),
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

        if (! DB::getSchemaBuilder()->hasTable($model->getTable())) {
            return [];
        }

        return $modelClass::query()
            ->orderBy($labelColumn)
            ->limit(500)
            ->pluck($labelColumn, 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private static function assignmentOptions(): array
    {
        return PerformanceAppraisalCycleAssignment::query()
            ->with(['employee', 'cycle'])
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (PerformanceAppraisalCycleAssignment $assignment): array => [
                $assignment->id => trim(($assignment->employee?->full_name ?? 'Employee #'.$assignment->employee_id).' - '.($assignment->cycle?->name ?? 'Cycle #'.$assignment->performance_appraisal_cycle_id)),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private static function goalPlanOptions(): array
    {
        return PerformanceGoalPlan::query()
            ->with(['employee', 'cycle'])
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (PerformanceGoalPlan $plan): array => [
                $plan->id => trim(($plan->employee?->full_name ?? 'Employee #'.$plan->employee_id).' - '.($plan->cycle?->name ?? 'Cycle #'.$plan->performance_appraisal_cycle_id)),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private static function appraisalOptions(): array
    {
        return PerformanceAppraisal::query()
            ->with(['employee', 'cycle'])
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (PerformanceAppraisal $appraisal): array => [
                $appraisal->id => trim(($appraisal->employee?->full_name ?? 'Employee #'.$appraisal->employee_id).' - '.($appraisal->cycle?->name ?? 'Cycle #'.$appraisal->performance_appraisal_cycle_id)),
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
            'active', 'approved', 'completed', 'closed', 'finalized', 'resolved', 'verified', 'confirm', 'confirmed', 'successfully_completed' => 'success',
            'draft', 'pending', 'proposed', 'employee_submitted', 'submitted', 'manager_review', 'moderation', 'open' => 'warning',
            'cancelled', 'rejected', 'terminated', 'unsuccessfully_completed' => 'danger',
            'extend', 'extended', 'deferred' => 'info',
            default => 'gray',
        };
    }
}
