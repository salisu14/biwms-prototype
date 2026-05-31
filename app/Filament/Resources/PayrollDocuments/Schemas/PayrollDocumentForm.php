<?php

namespace App\Filament\Resources\PayrollDocuments\Schemas;

use App\Enums\PayrollStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayCode;
use App\Models\PayrollPostingGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PayrollDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('Document Info')->schema([
                        TextInput::make('document_number')
                            ->default(fn () => 'PRL-'.date('Ym').'-'.rand(1000, 9999))
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        DatePicker::make('period_start')
                            ->required(),
                        DatePicker::make('period_end')
                            ->required(),
                        TextInput::make('working_days')
                            ->numeric()
                            ->default(30)
                            ->required(),
                        Select::make('payroll_period_id')
                            ->relationship('period', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->start_date->format('Y-m-d')} to {$record->end_date->format('Y-m-d')}")
                            ->label('Payroll Period'),
                        Select::make('status')
                            ->options(PayrollStatus::class)
                            ->default(PayrollStatus::OPEN->value)
                            ->required(),
                    ])->columns(2),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Lines')->schema([
                        Select::make('seed_department_id')
                            ->label('Filter by Department')
                            ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->dehydrated(false)
                            ->live(),
                        Select::make('seed_factory_code')
                            ->label('Filter by Location/Factory')
                            ->options(fn () => Employee::query()
                                ->whereNotNull('factory_code')
                                ->orderBy('factory_code')
                                ->distinct()
                                ->pluck('factory_code', 'factory_code')
                                ->all())
                            ->searchable()
                            ->dehydrated(false)
                            ->live(),
                        Select::make('seed_payroll_posting_group_id')
                            ->label('Filter by Payroll Posting Group')
                            ->options(fn () => PayrollPostingGroup::query()->orderBy('code')->pluck('code', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->dehydrated(false)
                            ->live(),
                        Select::make('employee_seed_ids')
                            ->label('Add Multiple Employees')
                            ->options(fn (Get $get) => Employee::query()
                                ->where('is_active', true)
                                ->when($get('seed_department_id'), fn ($query, $value) => $query->where('department_id', $value))
                                ->when($get('seed_factory_code'), fn ($query, $value) => $query->where('factory_code', $value))
                                ->when($get('seed_payroll_posting_group_id'), fn ($query, $value) => $query->where('payroll_posting_group_id', $value))
                                ->orderBy('employee_number')
                                ->get()
                                ->mapWithKeys(fn (Employee $employee) => [
                                    $employee->id => "{$employee->employee_number} - {$employee->first_name} {$employee->last_name}",
                                ])
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->dehydrated(false)
                            ->helperText('Select employees to seed payroll lines for review before posting. Missing payroll setup/bank account will be blocked during posting.')
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                $employeeIds = collect($state ?? [])->filter()->map(fn ($id) => (int) $id)->values();
                                if ($employeeIds->isEmpty()) {
                                    return;
                                }

                                $existingLines = collect($get('lines') ?? []);
                                $existingEmployeeIds = $existingLines->pluck('employee_id')->filter()->map(fn ($id) => (int) $id)->all();
                                $newIds = $employeeIds->reject(fn (int $id) => in_array($id, $existingEmployeeIds, true));
                                if ($newIds->isEmpty()) {
                                    return;
                                }

                                $defaultPayCode = PayCode::query()
                                    ->where('type', 'EARNING')
                                    ->orderBy('code')
                                    ->first();

                                $generatedLines = $newIds->map(fn (int $employeeId) => [
                                    'employee_id' => $employeeId,
                                    'pay_code_id' => $defaultPayCode?->id,
                                    'line_type' => 'Earning',
                                    'amount' => 0,
                                    'hours' => null,
                                    'rate' => null,
                                    'employer_amount' => null,
                                    'description' => 'Seeded line for payroll review',
                                ])->all();

                                $set('lines', array_values([...$existingLines->all(), ...$generatedLines]));
                            }),
                        Toggle::make('seed_select_all_filtered')
                            ->label('Select All Filtered Employees')
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                if (! $state) {
                                    return;
                                }

                                $employeeIds = Employee::query()
                                    ->where('is_active', true)
                                    ->when($get('seed_department_id'), fn ($query, $value) => $query->where('department_id', $value))
                                    ->when($get('seed_factory_code'), fn ($query, $value) => $query->where('factory_code', $value))
                                    ->when($get('seed_payroll_posting_group_id'), fn ($query, $value) => $query->where('payroll_posting_group_id', $value))
                                    ->orderBy('employee_number')
                                    ->pluck('id')
                                    ->map(fn ($id) => (int) $id)
                                    ->all();

                                $set('employee_seed_ids', $employeeIds);
                                $set('seed_select_all_filtered', false);
                            })
                            ->helperText('Quickly add all employees currently matched by the filters above.'),
                        Repeater::make('lines')
                            ->relationship()
                            ->minItems(1)
                            ->schema([
                                Select::make('employee_id')
                                    ->relationship('employee', 'employee_number', fn ($query) => $query
                                        ->where('is_active', true))
                                    ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->employee_number} - {$record->first_name} {$record->last_name}")
                                    ->required()
                                    ->columnSpan(2),
                                Select::make('pay_code_id')
                                    ->relationship('payCode', 'name')
                                    ->required()
                                    ->columnSpan(2),
                                Select::make('line_type')
                                    ->options([
                                        'Earning' => 'Earning',
                                        'Deduction' => 'Deduction',
                                        'Benefit' => 'Benefit',
                                    ])
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('amount')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('hours')
                                    ->numeric()
                                    ->columnSpan(2),
                                TextInput::make('rate')
                                    ->numeric()
                                    ->columnSpan(2),
                                TextInput::make('employer_amount')
                                    ->numeric()
                                    ->label('Employer Portion')
                                    ->columnSpan(2),
                                TextInput::make('description')
                                    ->columnSpan(6),
                                Textarea::make('review_note')
                                    ->dehydrated(false)
                                    ->rows(2)
                                    ->helperText('Optional reviewer note (not posted to payroll lines).')
                                    ->columnSpan(6),
                            ])->columns(6)
                            ->helperText('Review lines before approval/posting. You can seed multiple employees above, then adjust amounts here.')
                            ->reorderable(),
                    ]),
                ])->columnSpan(['lg' => 3]),
            ])->columns(3);
    }
}
