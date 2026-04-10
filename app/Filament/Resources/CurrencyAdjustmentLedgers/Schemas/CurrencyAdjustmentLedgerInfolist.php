<?php

namespace App\Filament\Resources\CurrencyAdjustmentLedgers\Schemas;

use App\Enums\CurrencyAdjustmentType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CurrencyAdjustmentLedgerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2) // Main layout: 2 Columns
                ->schema([
                    // LEFT COLUMN: Core Financial Data
                    Section::make('Financial Adjustment')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('document_no')
                                    ->label('Document No.')
                                    ->weight('bold'),
                                TextEntry::make('document_type')
                                    ->badge(),
                                TextEntry::make('posting_date')
                                    ->date(),
                            ]),

                            Grid::make(2)->schema([
                                TextEntry::make('adjustment_type')
                                    ->label('Adjustment Type')
                                    ->badge()
                                    ->color(fn(CurrencyAdjustmentType $state): string => match ($state) {
                                        CurrencyAdjustmentType::REALIZED_GAIN => 'success',
                                        CurrencyAdjustmentType::UNREALIZED_LOSS => 'danger',
                                    }),

                                TextEntry::make('currency.code')
                                    ->label('Currency')
                                    ->suffix(fn($record) => " ({$record->currency->symbol})"),
                            ]),

                            Grid::make(3)->schema([
                                TextEntry::make('original_amount')
                                    ->label('Original Amount')
                                    ->money(fn($record) => $record->currency->code),

                                TextEntry::make('adjusted_amount')
                                    ->label('Adjusted Amount')
                                    ->money(fn($record) => $record->currency->code),

                                TextEntry::make('adjustment_amount')
                                    ->label('Difference')
                                    ->money(fn($record) => $record->currency->code)
                                    ->weight('bold')
                                    ->color(fn($record) => $record->adjustment_type->isGain() ? 'success' : 'danger'),
                            ]),
                        ])->columnSpan(1),

                    // RIGHT COLUMN: Rates & Links
                    Section::make('Exchange Rates & Source')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('original_exch_rate')
                                    ->label('Original Rate')
                                    ->numeric(6),

                                TextEntry::make('new_exch_rate')
                                    ->label('New Rate')
                                    ->numeric(6),
                            ]),

                            TextEntry::make('adjustmentAccount.name')
                                ->label('Adjustment Account')
                                ->badge()
                                ->color('gray'),

                            Section::make('Linked Ledger Entries')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('vendorLedgerEntry.id')
                                            ->label('Vendor Entry')
                                            ->formatStateUsing(fn($state) => $state ? '#' . $state : '-')
                                            ->url(fn($record) => $record->vendorLedgerEntry ? route('filament.admin.resources.vendor-ledger-entries.view', $record->vendorLedgerEntry) : null)
                                            ->openUrlInNewTab(),

                                        TextEntry::make('customerLedgerEntry.id')
                                            ->label('Customer Entry')
                                            ->formatStateUsing(fn($state) => $state ? '#' . $state : '-')
                                            ->url(fn($record) => $record->customerLedgerEntry ? route('filament.admin.resources.customer-ledger-entries.view', $record->customerLedgerEntry) : null)
                                            ->openUrlInNewTab(),

                                        TextEntry::make('bankAccountLedgerEntry.id')
                                            ->label('Bank Entry')
                                            ->formatStateUsing(fn($state) => $state ? '#' . $state : '-')
                                            ->url(fn($record) => $record->bankAccountLedgerEntry ? route('filament.admin.resources.bank-account-ledger-entries.view', $record->bankAccountLedgerEntry) : null)
                                            ->openUrlInNewTab(),

                                        TextEntry::make('glEntry.id')
                                            ->label('G/L Entry')
                                            ->formatStateUsing(fn($state) => $state ? '#' . $state : '-')
                                            ->url(fn($record) => $record->glEntry ? route('filament.admin.resources.gl-entries.view', $record->glEntry) : null)
                                            ->openUrlInNewTab(),
                                    ]),
                                ])->collapsed(),
                        ])->columnSpan(1),
                ]),

                // BOTTOM: Description & Audit
                Section::make('Details & Audit')
                    ->schema([
                        TextEntry::make('description')
                            ->placeholder('No description provided.'),

                        Grid::make(3)->schema([
                            TextEntry::make('createdBy.name')
                                ->label('Created By'),

                            TextEntry::make('created_at')
                                ->label('Created At')
                                ->dateTime(),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime()
                                ->hidden(fn($record) => $record->updated_at->eq($record->created_at)),
                        ]),
                    ]),
            ]);
    }
}
