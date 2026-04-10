<?php

namespace App\Filament\Resources\Currencies\Schemas;

use App\Models\Currency;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CurrencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Replaced Split::make with Grid::make(2)
                Grid::make(2)
                    ->schema([
                        // Left Column (Identity & Posting Accounts)
                        Group::make([
                            Section::make('Identity')
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextEntry::make('code')->weight('bold'),
                                        TextEntry::make('description'),
                                        TextEntry::make('symbol'),
                                    ]),
                                    Grid::make(2)->schema([
                                        IconEntry::make('is_lcy')->label('Local Currency')->boolean(),
                                        IconEntry::make('is_active')->label('Status')->boolean(),
                                    ]),
                                ]),

                            Section::make('G/L Posting Accounts')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('realizedGainsAccount.name')->label('Realized Gains'),
                                        TextEntry::make('realizedLossesAccount.name')->label('Realized Losses'),
                                        TextEntry::make('unrealizedGainsAccount.name')->label('Unrealized Gains'),
                                        TextEntry::make('unrealizedLossesAccount.name')->label('Unrealized Losses'),
                                        TextEntry::make('receivablesAccount.name')->label('Receivables'),
                                        TextEntry::make('payablesAccount.name')->label('Payables'),
                                    ]),
                                ]),
                        ]), // Removed ->grow()

                        // Right Column (Rates & Rounding)
                        Group::make([
                            Section::make('Current Rates')
                                ->hidden(fn($record) => $record?->is_lcy)
                                ->schema([
                                    TextEntry::make('exchange_rate')->numeric(6)->size('lg')->weight('bold'),
                                    TextEntry::make('exchange_rate_date')->date(),
                                    TextEntry::make('exchange_rate_type')->badge(),
                                ]),
                            Section::make('Rounding Rules')
                                ->schema([
                                    TextEntry::make('rounding_method')->badge(),
                                    TextEntry::make('decimal_places')->suffix(' places'),
                                    TextEntry::make('amount_rounding_precision')->label('Precision'),
                                ]),
                        ]), // Removed ->columnSpan(1)
                    ]),
            ]);
    }
}
