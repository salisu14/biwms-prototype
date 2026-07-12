<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollPostingGroups\Schemas;

use App\Models\ChartOfAccount;
use App\Models\PayrollPostingGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PayrollPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Identification')
                    ->description('Define the classification code and description for this payroll segment.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Group Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->disabled(fn (?PayrollPostingGroup $record) => $record !== null)
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->placeholder('e.g., FULL-TIME')
                            ->helperText('The code is used for ledger mapping and cannot be changed after creation.'),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Standard full-time employee group'),
                    ]),

                Section::make('G/L Account Mapping')
                    ->description('Map payroll components to specific accounts in the Chart of Accounts.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('salaries_account_id')
                                ->label('Salaries Account (Expense)')
                                ->relationship('salariesAccount', 'account_number')
                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('wages_account_id')
                                ->label('Wages Account (Expense)')
                                ->relationship('wagesAccount', 'account_number')
                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                ->searchable()
                                ->preload()
                                ->helperText('Optional: Used for hourly or temporary staff.'),

                            Select::make('social_security_account_id')
                                ->label('Social Security (Liability)')
                                ->relationship('socialSecurityAccount', 'account_number')
                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('tax_payable_account_id')
                                ->label('Tax Payable (Liability)')
                                ->relationship('taxPayableAccount', 'account_number')
                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('net_pay_account_id')
                                ->label('Net Pay Account (Liability)')
                                ->relationship('netPayAccount', 'account_number')
                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpanFull()
                                ->helperText('The clearing account where net pay amounts are held before disbursement.'),
                        ]),
                    ]),
            ]);
    }
}
