<?php

namespace App\Filament\Resources\PayrollDocuments\Schemas;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\PayCode;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                        Select::make('employee_seed_ids')
                            ->label('Add Multiple Employees')
                            ->options(fn () => Employee::query()
                                ->where('is_active', true)
                                ->whereNotNull('payroll_posting_group_id')
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
                            ->helperText('Select employees to seed payroll lines for review before posting.')
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
                                    ->where('is_active', true)
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
                        Repeater::make('lines')
                            ->relationship()
                            ->schema([
                                Select::make('employee_id')
                                    ->relationship('employee', 'employee_number', fn ($query) => $query
                                        ->where('is_active', true)
                                        ->whereNotNull('payroll_posting_group_id'))
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
                            ->reorderable(),
                    ]),
                ])->columnSpan(['lg' => 3]),
            ])->columns(3);
    }
}
