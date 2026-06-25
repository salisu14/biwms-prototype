<?php

namespace App\Filament\Resources\Departments\Schemas;

use App\Enums\DepartmentStatus;
use App\Enums\DepartmentType;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Department Details')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-m-building-office')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('department_code')
                                        ->label('Code')
                                        ->required()
                                        ->unique(ignoreRecord: true),
                                    TextInput::make('name')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, callable $set) => $set('search_name', $state)),
                                    TextInput::make('search_name')
                                        ->label('Search Name'),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('parent_department_id')
                                        ->label('Parent Department')
                                        ->relationship('parentDepartment', 'name')
                                        ->searchable()
                                        ->preload(),
                                    Select::make('type')
                                        ->options(DepartmentType::class)
                                        ->default(DepartmentType::OPERATING)
                                        ->required()
                                        ->native(false),
                                    Select::make('status')
                                        ->options(DepartmentStatus::class)
                                        ->default(DepartmentStatus::ACTIVE)
                                        ->required()
                                        ->native(false),
                                    Select::make('location_code')
                                        ->options(Location::pluck('code', 'code'))
                                        ->placeholder('Select Location'),
                                ]),
                            ]),

                        Tab::make('Financial & Dimensions')
                            ->icon('heroicon-m-chart-bar')
                            ->schema([
                                Grid::make(3)->schema([
                                    Toggle::make('is_cost_center')->label('Cost Center'),
                                    Toggle::make('is_profit_center')->label('Profit Center'),
                                    Select::make('dimension_value_id')
                                        ->relationship('dimensionValue', 'name')
                                        ->label('Dimension Value'),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('annual_budget')
                                        ->numeric()
                                        ->prefix('₦'),
                                    TextInput::make('budget_utilized')
                                        ->numeric()
                                        ->prefix('₦')
                                        ->disabled()
                                        ->dehydrated(false),
                                    TextInput::make('cost_center_code'),
                                    TextInput::make('profit_center_code'),
                                    TextInput::make('default_expense_account'),
                                    TextInput::make('default_project_code'),
                                ]),
                            ]),

                        Tab::make('Contact & Administration')
                            ->icon('heroicon-m-user-group')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('manager_id')
                                        ->label('Department Manager')
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->options(fn (): array => Employee::query()
                                            ->orderBy('employee_number')
                                            ->limit(100)
                                            ->get()
                                            ->mapWithKeys(fn (Employee $employee): array => [
                                                $employee->id => "{$employee->employee_number} - {$employee->first_name} {$employee->last_name}",
                                            ])
                                            ->all())
                                        ->getSearchResultsUsing(
                                            fn (string $search) => Employee::query()
                                                ->where(function ($query) use ($search) {
                                                    $query->where('employee_number', 'like', "%{$search}%")
                                                        ->orWhere('first_name', 'like', "%{$search}%")
                                                        ->orWhere('last_name', 'like', "%{$search}%");
                                                })
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(fn (Employee $employee): array => [
                                                    $employee->id => "{$employee->employee_number} - {$employee->first_name} {$employee->last_name}",
                                                ])
                                        )
                                        ->getOptionLabelFromRecordUsing(
                                            fn (Employee $employee): string => "{$employee->employee_number} - {$employee->first_name} {$employee->last_name}"
                                        )
                                        ->afterStateUpdated(function ($state, Set $set): void {
                                            $employee = Employee::find($state);

                                            $set('email', $employee?->email);
                                            $set('phone', $employee?->phone);
                                        }),
                                    Select::make('approver_id')
                                        ->label('Default Approver')
                                        ->searchable()
                                        ->preload()
                                        ->helperText('Select the system user linked to the employee who approves this department.')
                                        ->options(fn (): array => User::query()
                                            ->whereHas('employee')
                                            ->with('employee')
                                            ->orderBy('name')
                                            ->limit(100)
                                            ->get()
                                            ->mapWithKeys(fn (User $user): array => [
                                                $user->id => $user->employee
                                                    ? "{$user->employee->employee_number} - {$user->employee->first_name} {$user->employee->last_name}"
                                                    : $user->name,
                                            ])
                                            ->all())
                                        ->getSearchResultsUsing(
                                            fn (string $search) => User::query()
                                                ->whereHas('employee', function ($query) use ($search) {
                                                    $query->where(function ($employeeQuery) use ($search) {
                                                        $employeeQuery->where('employee_number', 'like', "%{$search}%")
                                                            ->orWhere('first_name', 'like', "%{$search}%")
                                                            ->orWhere('last_name', 'like', "%{$search}%");
                                                    });
                                                })
                                                ->with('employee')
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(fn (User $user): array => [
                                                    $user->id => $user->employee
                                                        ? "{$user->employee->employee_number} - {$user->employee->first_name} {$user->employee->last_name}"
                                                        : $user->name,
                                                ])
                                        )
                                        ->getOptionLabelFromRecordUsing(
                                            fn (User $user): string => $user->employee
                                                ? "{$user->employee->employee_number} - {$user->employee->first_name} {$user->employee->last_name}"
                                                : $user->name
                                        ),
                                    TextInput::make('email')->email(),
                                    TextInput::make('phone')->tel(),
                                    TextInput::make('room_location'),
                                ]),
                                Grid::make(2)->schema([
                                    DatePicker::make('starting_date'),
                                    DatePicker::make('ending_date'),
                                ]),
                            ]),

                        Tab::make('System & Audit')
                            ->icon('heroicon-m-cog')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('level')->numeric()->disabled(),
                                    TextInput::make('department_path')->disabled(),
                                    TextInput::make('global_dimension_1_code')->label('Global Dim 1'),
                                ]),
                                Section::make('Blocking Info')
                                    ->schema([
                                        DateTimePicker::make('blocked_at')->disabled(),
                                        Select::make('blocked_by')
                                            ->relationship('blockedByUser', 'name')
                                            ->disabled(),
                                    ])->columns(2)->compact(),
                                Textarea::make('notes')->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
