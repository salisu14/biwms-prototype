<?php

namespace App\Filament\Resources\PettyCashTransactions\Schemas;

use App\Enums\PettyCashTransactionType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class PettyCashTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('petty_cash_fund_id')
                    ->label('Petty Cash Fund')
                    ->relationship('fund', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('petty_cash_voucher_id')
                    ->label('Voucher')
                    ->relationship('voucher', 'voucher_number')
                    ->searchable()
                    ->preload()
                    ->helperText('Leave empty if this is a manual replenishment.'),

                TextInput::make('transaction_number')
                    ->label('Transaction #')
                    ->required()
                    ->maxLength(50),

                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->default(now())
                    ->native(false),

                Select::make('type')
                    ->label('Type')
                    ->options(PettyCashTransactionType::class)
                    ->required(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->minValue(0.01)
                            ->step(0.01),

                        TextInput::make('running_balance')
                            ->label('Running Balance')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->step(0.01)
                            ->helperText('System-calculated balance after this transaction.'),
                    ]),

                Select::make('gl_entry_id')
                    ->label('G/L Entry')
                    ->relationship('glEntry', 'entry_number')
                    ->searchable()
                    ->preload()
                    ->helperText('Linked General Ledger entry.'),

                TextInput::make('description')
                    ->label('Description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('reference_number')
                    ->label('Reference #')
                    ->maxLength(100),
            ]);
    }
}
