<?php

namespace App\Filament\Resources\Departments\RelationManagers;

use App\Models\DepartmentEmployee;
use App\Models\Employee;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;  // ✅ Use this instead
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employeeAssignments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Employees';
    }

    protected static ?string $recordTitleAttribute = 'position_title';

    protected static ?string $title = 'Employees';

    protected static ?string $modelLabel = 'Employee Assignment';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('employee_id')
                    ->label('Employee')
                    ->relationship(
                        name: 'employee',
                        titleAttribute: 'first_name',
                        modifyQueryUsing: fn ($query) => $query->orderBy('last_name')->orderBy('first_name'),
                    )
                    ->searchable()
                    ->preload(false)
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn (Employee $record): string => "{$record->full_name} ({$record->employee_number})"
                    )
                    ->searchPrompt('Search by name or employee number...'),

                TextInput::make('position_title')
                    ->label('Position / Job Title')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g., Senior Accountant, IT Manager'),

                Select::make('assignment_type')
                    ->label('Assignment Type')
                    ->options([
                        'primary' => 'Primary Assignment',
                        'secondary' => 'Secondary Assignment',
                        'temporary' => 'Temporary Assignment',
                    ])
                    ->required()
                    ->default('primary')
                    ->helperText('Primary = main department, Secondary = additional role'),

                TextInput::make('allocation_percentage')
                    ->label('Time Allocation (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(100)
                    ->suffix('%')
                    ->required()
                    ->helperText('Percentage of time dedicated to this department'),

                DatePicker::make('assignment_date')
                    ->label('Start Date')
                    ->default(now())
                    ->required(),

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->nullable()
                    ->helperText('Leave empty for ongoing assignments'),

                Toggle::make('is_default_dimension')
                    ->label('Use as Default Dimension')
                    ->default(true)
                    ->helperText('Auto-populate this department on transactions'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('position_title')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->description(fn (DepartmentEmployee $record): string => $record->employee?->employee_number ?? ''
                    )
                    ->sortable()
                    ->searchable(['first_name', 'last_name', 'employee_number'])
                    ->weight('font-medium'),

                TextColumn::make('position_title')
                    ->label('Position')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('assignment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'primary' => 'success',
                        'secondary' => 'warning',
                        'temporary' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('allocation_percentage')
                    ->label('Allocation')
                    ->suffix('%')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (string $state): string => $state >= 100 ? 'success' : ($state >= 50 ? 'warning' : 'danger')
                    ),

                TextColumn::make('assignment_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('Ongoing')
                    ->formatStateUsing(function ($state): string {
                        if (! $state) {
                            return 'Ongoing';
                        }

                        return now()->gt($state) ? 'Expired' : $state->format('M d, Y');
                    }),

                // ✅ FIXED: Use IconColumn instead of ToggleColumn
                IconColumn::make('is_default_dimension')
                    ->label('Default')
                    ->alignCenter()
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->defaultSort('assignment_date', 'desc')
            ->filters([
                TernaryFilter::make('active_status')
                    ->label('Active Assignments')
                    ->queries(
                        true: fn ($query) => $query->whereNull('end_date')
                            ->orWhere('end_date', '>=', now()),
                        false: fn ($query) => $query->whereNotNull('end_date')
                            ->where('end_date', '<', now()),
                    ),

                SelectFilter::make('assignment_type')
                    ->options([
                        'primary' => 'Primary',
                        'secondary' => 'Secondary',
                        'temporary' => 'Temporary',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['department_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    })
                    ->successNotificationTitle('Employee assigned successfully'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->successNotificationTitle('Assignments deleted'),
                ]),
            ])
            ->emptyStateHeading('No employees assigned yet')
            ->emptyStateDescription('Start by assigning employees to this department.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->modifyQueryUsing(fn ($query) => $query->with('employee'));
    }
}
