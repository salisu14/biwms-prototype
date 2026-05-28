<?php

namespace App\Filament\Resources\PayrollDocuments\Schemas;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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
                            ])->columns(6)
                            ->reorderable(),
                    ]),
                ])->columnSpan(['lg' => 3]),
            ])->columns(3);
    }
}
