<?php

namespace App\Filament\Resources\SalesInvoices\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class SalesInvoiceInfolist
{
    public static function configure(Schema $infolist): Schema
    {
        return $infolist
            ->schema([
                Section::make('Invoice Overview')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->weight('bold')
                            ->label('Number'),

                        TextEntry::make('customer.name')
                            ->label('Customer'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => method_exists($state, 'color') ? $state->color() : 'primary'),

                        TextEntry::make('invoice_date')
                            ->date(),

                        TextEntry::make('due_date')
                            ->date(),

                        TextEntry::make('total_amount')
                            ->money(fn ($record) => $record->currency_code ?? 'USD')
                            ->weight('black')
                            ->size(TextSize::Large),
                    ]),

                Section::make('Line Items')
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('description'),

                                TextEntry::make('quantity')
                                    ->numeric(),

                                TextEntry::make('unit_price')
                                    ->money('NGN'),

                                TextEntry::make('line_total')
                                    ->label('Total')
                                    ->money('NGN')
                                    ->weight('bold'),
                            ])
                            ->columns(4),
                    ]),

                Grid::make(1)
                    ->schema([
                        Section::make('System Information')
                            ->compact()
                            ->schema([
                                Group::make([
                                    TextEntry::make('posted_at')
                                        ->dateTime()
                                        ->placeholder('Not yet posted'),

                                    TextEntry::make('posted_by')
                                        ->placeholder('N/A'),
                                ])->columns(2),
                            ]),
                    ]),
            ]);
    }
}
