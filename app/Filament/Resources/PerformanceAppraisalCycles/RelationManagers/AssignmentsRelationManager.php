<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\RelationManagers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformanceAppraisalCycleAssignment;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Employee Assignments';

    protected static ?string $recordTitleAttribute = 'employee_id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->label('Employee')
                    ->relationship(
                        name: 'employee',
                        titleAttribute: 'employee_number',
                        modifyQueryUsing: function (
                            Builder $query
                        ): Builder {
                            return $query
                                ->where('is_active', true)
                                ->whereDoesntHave(
                                    'cycleAssignments',
                                    function (Builder $query): void {
                                        $query->where(
                                            'performance_appraisal_cycle_id',
                                            $this->getOwnerRecord()->getKey()
                                        );
                                    }
                                )
                                ->orderBy('first_name')
                                ->orderBy('last_name');
                        }
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Employee $record): string =>
                        self::employeeLabel($record)
                    )
                    ->searchable([
                        'employee_number',
                        'first_name',
                        'last_name',
                    ])
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),

                Select::make('manager_employee_id')
                    ->label('Primary Reviewer')
                    ->relationship(
                        name: 'manager',
                        titleAttribute: 'employee_number',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query
                            ->where('is_active', true)
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Employee $record): string =>
                        self::employeeLabel($record)
                    )
                    ->searchable([
                        'employee_number',
                        'first_name',
                        'last_name',
                    ])
                    ->preload()
                    ->nullable()
                    ->different('employee_id'),

                Select::make('secondary_reviewer_employee_id')
                    ->label('Secondary Reviewer')
                    ->relationship(
                        name: 'secondaryReviewer',
                        titleAttribute: 'employee_number',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query
                            ->where('is_active', true)
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Employee $record): string =>
                        self::employeeLabel($record)
                    )
                    ->searchable([
                        'employee_number',
                        'first_name',
                        'last_name',
                    ])
                    ->preload()
                    ->nullable()
                    ->different('employee_id')
                    ->different('manager_employee_id')
                    ->visible(
                        fn (): bool =>
                        (bool) $this->getOwnerRecord()
                            ->allow_secondary_reviewer
                    ),

                Select::make('appraisal_template_id')
                    ->label('Appraisal Template')
                    ->relationship(
                        name: 'template',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query->orderBy('name')
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('rating_scale_id')
                    ->label('Rating Scale')
                    ->relationship(
                        name: 'ratingScale',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query
                            ->where('is_active', true)
                            ->orderBy('name')
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText(
                        'Leave blank to use the cycle default rating scale.'
                    ),

                Select::make('eligibility_status')
                    ->label('Eligibility')
                    ->options([
                        'eligible' => 'Eligible',
                        'ineligible' => 'Ineligible',
                        'pending' => 'Pending Review',
                    ])
                    ->default('eligible')
                    ->required()
                    ->live(),

                Textarea::make('exclusion_reason')
                    ->label('Exclusion Reason')
                    ->rows(2)
                    ->maxLength(1000)
                    ->required(
                        fn (Get $get): bool =>
                            $get('eligibility_status') === 'ineligible'
                    )
                    ->visible(
                        fn (Get $get): bool =>
                            $get('eligibility_status') === 'ineligible'
                    ),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('employee_id')
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with([
                    'employee.department',
                    'manager',
                    'secondaryReviewer',
                    'template',
                    'ratingScale',
                ])
            )
            ->defaultSort('assigned_at', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable([
                        'first_name',
                        'last_name',
                        'employee_number',
                    ])
                    ->sortable([
                        'first_name',
                        'last_name',
                    ])
                    ->weight('medium')
                    ->description(
                        fn (
                            PerformanceAppraisalCycleAssignment $record
                        ): string =>
                            $record->employee?->department?->name ?? ''
                    ),

                TextColumn::make('manager.full_name')
                    ->label('Reviewer')
                    ->searchable([
                        'first_name',
                        'last_name',
                        'employee_number',
                    ])
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('secondaryReviewer.full_name')
                    ->label('Secondary Reviewer')
                    ->searchable([
                        'first_name',
                        'last_name',
                        'employee_number',
                    ])
                    ->placeholder('—')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('template.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->toggleable(),

                TextColumn::make('ratingScale.name')
                    ->label('Rating Scale')
                    ->placeholder('Cycle default')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('eligibility_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                        match ($state) {
                            'eligible' => 'Eligible',
                            'ineligible' => 'Ineligible',
                            'pending' => 'Pending Review',
                            default => ucfirst(
                                (string) $state
                            ),
                        }
                    )
                    ->color(
                        fn (?string $state): string =>
                        match ($state) {
                            'eligible' => 'success',
                            'ineligible' => 'danger',
                            'pending' => 'warning',
                            default => 'gray',
                        }
                    ),

                TextColumn::make('assigned_at')
                    ->label('Assigned')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                SelectFilter::make('eligibility_status')
                    ->label('Eligibility')
                    ->options([
                        'eligible' => 'Eligible',
                        'ineligible' => 'Ineligible',
                        'pending' => 'Pending',
                    ]),

                SelectFilter::make('department_id')
                    ->label('Department')
                    ->options(
                        fn (): array => Department::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Employee Assignment')
                    ->visible(
                        fn (): bool => ! $this->cycleIsLocked()
                    )
                    ->mutateDataUsing(
                        function (array $data): array {
                            $employee = Employee::query()
                                ->with([
                                    'department',
                                    'manager',
                                ])
                                ->findOrFail(
                                    $data['employee_id']
                                );

                            $this->ensureEmployeeNotAssigned(
                                $employee->getKey()
                            );

                            $data['department_id'] =
                                $employee->department_id;

                            $data['employment_status_snapshot'] =
                                $employee->employment_status;

                            $data['position_snapshot'] =
                                $employee->position;

                            $data['grade_snapshot'] =
                                $employee->grade;

                            $data['department_snapshot'] =
                                $employee->department?->name;

                            $data['manager_snapshot'] =
                                $employee->manager?->full_name;

                            $data['rating_scale_id'] ??=
                                $this->getOwnerRecord()
                                    ->rating_scale_id;

                            $data['assigned_by'] = auth()->id();
                            $data['assigned_at'] = now();

                            return $data;
                        }
                    ),

                Action::make('bulkImport')
                    ->label('Bulk Import Employees')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(
                        fn (): bool => ! $this->cycleIsLocked()
                    )
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->options(
                                fn (): array =>
                                Department::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('manager_employee_id')
                            ->label('Default Reviewer')
                            ->options(
                                fn (): array =>
                                Employee::query()
                                    ->where('is_active', true)
                                    ->orderBy('first_name')
                                    ->orderBy('last_name')
                                    ->get()
                                    ->mapWithKeys(
                                        fn (
                                            Employee $employee
                                        ): array => [
                                            $employee->getKey() =>
                                                self::employeeLabel(
                                                    $employee
                                                ),
                                        ]
                                    )
                                    ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('appraisal_template_id')
                            ->label('Template')
                            ->relationship(
                                'template',
                                'name'
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Toggle::make('exclude_existing')
                            ->label(
                                'Exclude Already Assigned Employees'
                            )
                            ->default(true),
                    ])
                    ->action(
                        function (array $data): void {
                            $this->ensureCycleIsEditable();

                            $cycle = $this->getOwnerRecord();

                            $employees = Employee::query()
                                ->with([
                                    'department',
                                    'manager',
                                ])
                                ->where(
                                    'department_id',
                                    $data['department_id']
                                )
                                ->where('is_active', true)
                                ->orderBy('id');

                            if (
                                (bool) (
                                    $data['exclude_existing']
                                    ?? true
                                )
                            ) {
                                $employees->whereDoesntHave(
                                    'cycleAssignments',
                                    fn (
                                        Builder $query
                                    ): Builder => $query->where(
                                        'performance_appraisal_cycle_id',
                                        $cycle->getKey()
                                    )
                                );
                            }

                            $imported = 0;
                            $skipped = 0;

                            $employees->chunkById(
                                100,
                                function (
                                    Collection $employees
                                ) use (
                                    $data,
                                    $cycle,
                                    &$imported,
                                    &$skipped
                                ): void {
                                    foreach (
                                        $employees as $employee
                                    ) {
                                        $assignment =
                                            PerformanceAppraisalCycleAssignment::query()
                                                ->firstOrCreate(
                                                    [
                                                        'performance_appraisal_cycle_id' =>
                                                            $cycle->getKey(),
                                                        'employee_id' =>
                                                            $employee->getKey(),
                                                    ],
                                                    [
                                                        'department_id' =>
                                                            $employee
                                                                ->department_id,

                                                        'manager_employee_id' =>
                                                            $data[
                                                            'manager_employee_id'
                                                            ] ?? null,

                                                        'secondary_reviewer_employee_id' =>
                                                            null,

                                                        'appraisal_template_id' =>
                                                            $data[
                                                            'appraisal_template_id'
                                                            ],

                                                        'rating_scale_id' =>
                                                            $cycle
                                                                ->rating_scale_id,

                                                        'employment_status_snapshot' =>
                                                            $employee
                                                                ->employment_status,

                                                        'position_snapshot' =>
                                                            $employee
                                                                ->position,

                                                        'grade_snapshot' =>
                                                            $employee
                                                                ->grade,

                                                        'department_snapshot' =>
                                                            $employee
                                                                ->department
                                                                ?->name,

                                                        'manager_snapshot' =>
                                                            $employee
                                                                ->manager
                                                                ?->full_name,

                                                        'eligibility_status' =>
                                                            'eligible',

                                                        'assigned_by' =>
                                                            auth()->id(),

                                                        'assigned_at' =>
                                                            now(),
                                                    ]
                                                );

                                        if (
                                            $assignment
                                                ->wasRecentlyCreated
                                        ) {
                                            $imported++;
                                        } else {
                                            $skipped++;
                                        }
                                    }
                                }
                            );

                            Notification::make()
                                ->title(
                                    'Employee import completed'
                                )
                                ->body(
                                    "{$imported} imported; "
                                    . "{$skipped} already assigned."
                                )
                                ->success()
                                ->send();
                        }
                    ),

            ])
            ->recordActions([
                EditAction::make()
                    ->visible(
                        fn (): bool => ! $this->cycleIsLocked()
                    )
                    ->mutateDataUsing(
                        function (array $data): array {
                            $data['rating_scale_id'] ??=
                                $this->getOwnerRecord()
                                    ->rating_scale_id;

                            return $data;
                        }
                    ),

                DeleteAction::make()
                    ->visible(
                        fn (): bool => ! $this->cycleIsLocked()
                    )
                    ->before(
                        fn (): mixed =>
                        $this->ensureCycleIsEditable()
                    ),

                Action::make('viewAppraisal')
                    ->label('View Appraisal')
                    ->icon('heroicon-o-eye')
                    ->visible(
                        fn (
                            PerformanceAppraisalCycleAssignment $record
                        ): bool =>
                        $record->appraisals()->exists()
                    )
                    ->url(
                        function (
                            PerformanceAppraisalCycleAssignment $record
                        ): string {
                            $appraisalId = $record
                                ->appraisals()
                                ->value('id');

                            return route(
                                'filament.admin.resources.performance-appraisals.view',
                                [
                                    'record' => $appraisalId,
                                ]
                            );
                        }
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(
                            fn (): bool =>
                            ! $this->cycleIsLocked()
                        )
                        ->before(
                            fn (): mixed =>
                            $this->ensureCycleIsEditable()
                        ),

                    BulkAction::make('changeReviewer')
                        ->label('Change Reviewer')
                        ->icon('heroicon-o-user-plus')
                        ->visible(
                            fn (): bool =>
                            ! $this->cycleIsLocked()
                        )
                        ->schema([
                            Select::make(
                                'manager_employee_id'
                            )
                                ->label('New Reviewer')
                                ->options(
                                    fn (): array =>
                                    Employee::query()
                                        ->where(
                                            'is_active',
                                            true
                                        )
                                        ->orderBy(
                                            'first_name'
                                        )
                                        ->orderBy(
                                            'last_name'
                                        )
                                        ->get()
                                        ->mapWithKeys(
                                            fn (
                                                Employee $employee
                                            ): array => [
                                                $employee
                                                    ->getKey() =>
                                                    self::employeeLabel(
                                                        $employee
                                                    ),
                                            ]
                                        )
                                        ->all()
                                )
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(
                            function (
                                Collection $records,
                                array $data
                            ): void {
                                $this->ensureCycleIsEditable();

                                PerformanceAppraisalCycleAssignment::query()
                                    ->whereKey(
                                        $records->modelKeys()
                                    )
                                    ->update([
                                        'manager_employee_id' =>
                                            $data[
                                            'manager_employee_id'
                                            ],
                                    ]);
                            }
                        )
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('changeTemplate')
                        ->label('Change Template')
                        ->icon(
                            'heroicon-o-document-duplicate'
                        )
                        ->visible(
                            fn (): bool =>
                            ! $this->cycleIsLocked()
                        )
                        ->schema([
                            Select::make(
                                'appraisal_template_id'
                            )
                                ->label('New Template')
                                ->relationship(
                                    'template',
                                    'name'
                                )
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(
                            function (
                                Collection $records,
                                array $data
                            ): void {
                                $this->ensureCycleIsEditable();

                                PerformanceAppraisalCycleAssignment::query()
                                    ->whereKey(
                                        $records->modelKeys()
                                    )
                                    ->update([
                                        'appraisal_template_id' =>
                                            $data[
                                            'appraisal_template_id'
                                            ],
                                    ]);
                            }
                        )
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading(
                'No employees assigned yet'
            )
            ->emptyStateDescription(
                'Add employees to this appraisal cycle to begin the process.'
            )
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Add Employee Assignment')
                    ->visible(
                        fn (): bool =>
                        ! $this->cycleIsLocked()
                    ),
            ]);
    }

    public function isReadOnly(): bool
    {
        return $this->cycleIsLocked();
    }

    private function cycleIsLocked(): bool
    {
        return (bool) $this->getOwnerRecord()->isLocked();
    }

    private function ensureCycleIsEditable(): void
    {
        if (! $this->cycleIsLocked()) {
            return;
        }

        Notification::make()
            ->title('Appraisal cycle is locked')
            ->body(
                'Assignments cannot be modified after the cycle has been locked.'
            )
            ->danger()
            ->send();

        throw ValidationException::withMessages([
            'cycle' =>
                'Assignments cannot be modified in a locked appraisal cycle.',
        ]);
    }

    private function ensureEmployeeNotAssigned(
        int|string $employeeId
    ): void {
        $exists = PerformanceAppraisalCycleAssignment::query()
            ->where(
                'performance_appraisal_cycle_id',
                $this->getOwnerRecord()->getKey()
            )
            ->where('employee_id', $employeeId)
            ->exists();

        if (! $exists) {
            return;
        }

        throw ValidationException::withMessages([
            'employee_id' =>
                'This employee is already assigned to the appraisal cycle.',
        ]);
    }

    private static function employeeLabel(
        Employee $employee
    ): string {
        $name = trim((string) $employee->full_name);

        if (blank($employee->employee_number)) {
            return $name;
        }

        return "{$name} ({$employee->employee_number})";
    }
}
