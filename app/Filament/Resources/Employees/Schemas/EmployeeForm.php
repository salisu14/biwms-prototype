<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\EmployeeAssignmentType;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Employee;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basic Information')
                ->schema([
                    TextInput::make('employee_number')
                        ->label('Employee No.')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20)
                        // Lock the field if the record already exists in the database
                        ->disabled(fn (?Employee $record) => $record !== null)
                        // Ensure the value is still sent to the database during creation
                        ->dehydrated()
                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                        ->helperText('The number cannot be changed once the Employee is created.'),
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('job_title')
                        ->maxLength(255),
                    ToggleButtons::make('assignment_type')
                        ->label('Assignment Type')
                        ->options(EmployeeAssignmentType::class)
                        ->inline()
                        ->required()
                        ->live()
                        ->default(EmployeeAssignmentType::Corporate),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->validationMessages([
                            'unique' => 'This email is already assigned to another employee.',
                        ]),
                    TextInput::make('phone')
                        ->tel()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                ])->columns(2),

            Section::make('Organizational Assignment')
                ->description('Dimensions used for financial and operational analysis.')
                ->schema([
                    Select::make('business_code')
                        ->label('Business')
                        ->options(function () {
                            $dim = Dimension::where('code', 'BUSINESS')->first();

                            return $dim ? $dim->values()->pluck('name', 'code') : [];
                        })
                        ->live()
                        ->required()
                        ->visible(fn (Get $get) => $get('assignment_type') === EmployeeAssignmentType::Factory->value),

                    Select::make('factory_code')
                        ->label('Factory')
                        ->options(function (Get $get) {
                            $businessCode = $get('business_code');
                            if (! $businessCode) {
                                return [];
                            }

                            $businessValue = DimensionValue::where('code', $businessCode)
                                ->whereHas('dimension', fn ($q) => $q->where('code', 'BUSINESS'))
                                ->first();

                            if (! $businessValue) {
                                return [];
                            }

                            $factoryDim = Dimension::where('code', 'FACTORY')->first();
                            if (! $factoryDim) {
                                return [];
                            }

                            return $factoryDim->values()
                                ->where('parent_id', $businessValue->id)
                                ->pluck('name', 'code');
                        })
                        ->required()
                        ->visible(fn (Get $get) => $get('assignment_type') === EmployeeAssignmentType::Factory->value),

                    Select::make('department_id')
                        ->label('Department')
                        ->relationship('department', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                ])->columns(3),

            Section::make('Financial Information')
                ->description('Posting groups for accounting and GL entries.')
                ->schema([
                    Select::make('employee_posting_group_id')
                        ->label('Employee Posting Group')
                        ->relationship('employeePostingGroup', 'code')
                        ->required(),
                    Select::make('payroll_posting_group_id')
                        ->label('Payroll Posting Group')
                        ->relationship('payrollPostingGroup', 'code')
                        ->required(),
                ]),
        ]);
    }
}
