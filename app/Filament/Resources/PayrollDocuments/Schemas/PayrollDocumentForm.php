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
                        Select::make('status')
                            ->options(PayrollStatus::class)
                            ->default(PayrollStatus::DRAFT->value)
                            ->required(),
                    ])->columns(2),
                ])->columnSpan(['lg' => 2]),

                Group::make([
                    Section::make('Lines')->schema([
                        Repeater::make('lines')
                            ->relationship()
                            ->schema([
                                Select::make('employee_id')
                                    ->relationship('employee', 'employee_number')
                                    ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->employee_number} - {$record->first_name} {$record->last_name}")
                                    ->required()
                                    ->columnSpan(2),
                                Select::make('pay_code_id')
                                    ->relationship('payCode', 'name')
                                    ->required()
                                    ->columnSpan(2),
                                TextInput::make('amount')
                                    ->numeric()
                                    ->required()
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
