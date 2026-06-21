<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\EmployeeAssignmentType;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Factory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

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
                        ->disabled(fn (?Employee $record) => $record !== null)
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
                        // Use ->value to force the default state to be a string
                        ->default(EmployeeAssignmentType::Corporate->value)
                        ->afterStateUpdated(function (callable $set) {
                            $set('business_code', null);
                            $set('factory_code', null);
                        }),
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
                            // Query the actual Business model
                            return Business::query()
                                ->orderBy('name')
                                ->pluck('name', 'code');
                        })
                        ->live()
                        ->required()
                        ->visible(fn (Get $get): bool => $get('assignment_type') === EmployeeAssignmentType::Factory
                            || $get('assignment_type') === EmployeeAssignmentType::Factory->value
                        )
                        ->dehydrated(fn (Get $get): bool => $get('assignment_type') === EmployeeAssignmentType::Factory
                            || $get('assignment_type') === EmployeeAssignmentType::Factory->value
                        )
                        ->afterStateUpdated(fn (callable $set) => $set('factory_code', null)),

                    Select::make('factory_code')
                        ->label('Factory')
                        ->options(function (Get $get) {
                            $businessCode = $get('business_code');
                            if (! $businessCode) {
                                return [];
                            }

                            // Find the selected Business to get its ID
                            $business = Business::where('code', $businessCode)->first();
                            if (! $business) {
                                return [];
                            }

                            // Query factories linked to this specific business_id
                            return Factory::query()
                                ->where('business_id', $business->id)
                                ->where('is_active', true) // Optional: only show active factories
                                ->orderBy('name')
                                ->pluck('name', 'code');
                        })
                        ->required()
                        ->visible(fn (Get $get): bool => $get('assignment_type') === EmployeeAssignmentType::Factory
                            || $get('assignment_type') === EmployeeAssignmentType::Factory->value
                        )
                        ->dehydrated(fn (Get $get): bool => $get('assignment_type') === EmployeeAssignmentType::Factory
                            || $get('assignment_type') === EmployeeAssignmentType::Factory->value
                        ),

                    Select::make('department_id')
                        ->label('Department')
                        ->relationship('department', 'name')
                        ->searchable()
                        ->preload(),
                ])->columns(3),

            Section::make('Financial Information')
                ->description('Posting groups for accounting and GL entries.')
                ->schema([
                    Select::make('employee_posting_group_id')
                        ->label('Employee Posting Group')
                        ->relationship('employeePostingGroup', 'code'),
                    Select::make('payroll_posting_group_id')
                        ->label('Payroll Posting Group')
                        ->relationship('payrollPostingGroup', 'code')
                        ->required(),
                ]),

            // NEW: User Account Section
            Section::make('System Access')
                ->description('Create a login account for this employee. Only shown during creation.')
                ->schema([
                    Toggle::make('create_user_account')
                        ->label('Create Login Account')
                        ->live()
                        ->helperText('Enable to grant this employee access to the system.'),

                    TextInput::make('login_email')
                        ->label('Login Email')
                        ->email()
                        ->required()
                        ->unique(table: 'users', column: 'email')
                        ->validationMessages(['unique' => 'This login email is already in use by another user.'])
                        ->visible(fn (Get $get) => $get('create_user_account')),

                    Select::make('initial_role')
                        ->label('Initial Role')
                        ->required()
                        ->options(fn () => Role::query()->pluck('name', 'name'))
                        ->visible(fn (Get $get) => $get('create_user_account')),

                    ToggleButtons::make('password_method')
                        ->label('Password Setup Method')
                        ->options([
                            'send_password_reset' => 'Send Password Reset Link',
                            'temporary_password' => 'Set Temporary Password',
                        ])
                        ->inline()
                        ->required()
                        ->default('send_password_reset')
                        ->visible(fn (Get $get) => $get('create_user_account'))
                        ->live(),

                    TextInput::make('temporary_password')
                        ->label('Temporary Password')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->visible(fn (Get $get) => $get('create_user_account') && $get('password_method') === 'temporary_password')
                        ->helperText('User will be forced to change this on first login.'),
                ])
                ->visible(fn (?Employee $record) => $record === null), // Hide entirely on edit
        ]);
    }
}
