<?php

namespace App\Filament\Resources\PettyCashTransactions\Schemas;

use App\Enums\PettyCashTransactionType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PettyCashTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        TextEntry::make('transaction_number')
                            ->label('Transaction #')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('date')
                            ->label('Date')
                            ->date('d/m/Y'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->color(fn (PettyCashTransactionType $state): string => $state->color() ?? 'gray'),
                    ])->columns(3),

                Section::make('Source & References')
                    ->schema([
                        TextEntry::make('fund.name')
                            ->label('Petty Cash Fund'),

                        TextEntry::make('voucher.voucher_number')
                            ->label('Voucher #')
                            ->placeholder('N/A')
                            ->color('primary'),

                        TextEntry::make('glEntry.entry_number')
                            ->label('G/L Entry #')
                            ->placeholder('N/A')
                            ->color('primary'),

                        TextEntry::make('reference_number')
                            ->label('Reference #')
                            ->placeholder('N/A'),
                    ])->columns(2),

                Section::make('Amounts')
                    ->schema([
                        TextEntry::make('amount')
                            ->label('Amount')
                            ->formatStateUsing(fn ($state) => \Illuminate\Support\Number::currency($state, 'NGN'))
                            ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                            ->weight('bold'),

                        TextEntry::make('running_balance')
                            ->label('Running Balance')
                            ->formatStateUsing(fn ($state) => \Illuminate\Support\Number::currency($state, 'NGN')),
                    ])->columns(2),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),

                Section::make([
                    TextEntry::make('created_at')
                        ->label('Created At')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('updated_at')
                        ->label('Updated At')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('-'),
                ])->columns(2),
            ]);
    }
}
