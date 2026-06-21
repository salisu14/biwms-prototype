<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Schemas;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerLedgerEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Entry Summary')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('entry_number')
                            ->label('Entry No.'),
                        TextEntry::make('customer.name')
                            ->label('Customer')
                            ->formatStateUsing(fn ($state, $record) => $record->customer
                                ? "{$record->customer->customer_number} - {$record->customer->name}"
                                : 'Unknown Customer'),
                        TextEntry::make('document_type')
                            ->badge(),
                    ]),
                ]),
            Section::make('Amounts')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('amount')
                            ->label('Amount')
                            ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->currency_code ?? config('app.default_currency', 'USD'))),
                        TextEntry::make('debit_amount')
                            ->label('Debit')
                            ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->currency_code ?? config('app.default_currency', 'USD'))),
                        TextEntry::make('credit_amount')
                            ->label('Credit')
                            ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->currency_code ?? config('app.default_currency', 'USD'))),
                        TextEntry::make('remaining_amount')
                            ->label('Remaining')
                            ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->currency_code ?? config('app.default_currency', 'USD'))),
                    ]),
                ]),
            Section::make('Dates & Reference')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('posting_date')
                            ->date(),
                        TextEntry::make('document_date')
                            ->date(),
                        TextEntry::make('due_date')
                            ->date(),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('document_number')
                            ->label('Document No.'),
                        TextEntry::make('external_document_number')
                            ->label('External Ref.'),
                    ]),
                ]),
            Section::make('Notes')
                ->schema([
                    Group::make([
                        TextEntry::make('description')
                            ->columnSpanFull(),
                        TextEntry::make('comment')
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }
}
