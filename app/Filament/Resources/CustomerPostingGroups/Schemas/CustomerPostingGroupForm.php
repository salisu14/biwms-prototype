<?php

namespace App\Filament\Resources\CustomerPostingGroups\Schemas;

use App\Models\ChartOfAccount;
use App\Models\CustomerPostingGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->description('Define the primary code and status for this customer classification.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Group Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->disabled(fn (?CustomerPostingGroup $record) => $record !== null)
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->placeholder('e.g., DOMESTIC'),

                        TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Local market customer group'),

                        Toggle::make('blocked')
                            ->label('Blocked from usage')
                            ->onColor('danger')
                            ->helperText('If enabled, this group cannot be assigned to new customers or used in journals.')
                            ->default(false)
                            ->inline(false),
                    ]),

                Section::make('Primary G/L Accounts')
                    ->description('Map the main receivables account for this group.')
                    ->schema([
                        Select::make('receivables_account_id')
                            ->label('Receivables Account (A/R)')
                            ->relationship('receivablesAccount', 'account_number')
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The balance sheet account for customer debt.'),
                    ]),

                Section::make('Discounts & Rounding')
                    ->description('Configure accounts for financial adjustments and invoice precision.')
                    ->columns(2)
                    ->schema([
                        Select::make('payment_disc_debit_account_id')
                            ->label('Pmt. Disc. Debit Acc.')
                            ->relationship('receivablesAccount', 'account_number') // Reusing relationship logic for COA
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                            ->searchable()
                            ->preload(),

                        Select::make('payment_disc_credit_account_id')
                            ->label('Pmt. Disc. Credit Acc.')
                            ->relationship('receivablesAccount', 'account_number')
                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                            ->searchable()
                            ->preload(),

                        Grid::make(3)
                            ->schema([
                                Select::make('invoice_rounding_account_id')
                                    ->label('Inv. Rounding Account')
                                    ->relationship('receivablesAccount', 'account_number')
                                    ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                    ->searchable()
                                    ->preload(),

                                Select::make('debit_rounding_account_id')
                                    ->label('Debit Rounding')
                                    ->relationship('receivablesAccount', 'account_number')
                                    ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                    ->searchable()
                                    ->preload(),

                                Select::make('credit_rounding_account_id')
                                    ->label('Credit Rounding')
                                    ->relationship('receivablesAccount', 'account_number')
                                    ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                    ->searchable()
                                    ->preload(),
                            ])
                            ->columnSpanFull(), // This ensures the grid uses the full width of the section
                    ])->collapsible(),
            ]);
    }
}
