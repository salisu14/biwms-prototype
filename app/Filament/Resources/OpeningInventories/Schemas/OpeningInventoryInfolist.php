<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories\Schemas;

use App\Models\OpeningInventory;
use App\Models\ValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OpeningInventoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(12)->schema([
                Section::make('Document Header')
                    ->columnSpan(8)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('document_number')->label('Document No.')->weight('bold')->color('primary'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                OpeningInventory::STATUS_DRAFT => 'warning',
                                OpeningInventory::STATUS_POSTED => 'success',
                                OpeningInventory::STATUS_CANCELLED => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('business.name')->placeholder('Global'),
                        TextEntry::make('source')->badge(),
                        TextEntry::make('description')->columnSpanFull()->placeholder('No description.'),
                    ]),

                Section::make('Posting Metadata')
                    ->columnSpan(4)
                    ->schema([
                        TextEntry::make('posting_date')->date(),
                        TextEntry::make('createdBy.name')->label('Created By')->placeholder('System'),
                        TextEntry::make('postedBy.name')->label('Posted By')->placeholder('—'),
                        TextEntry::make('posted_at')->dateTime()->placeholder('—'),
                    ]),

                Section::make('Opening Lines')
                    ->columnSpan(12)
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->columns([
                                'default' => 1,
                                'md' => 3,
                                'xl' => 6,
                            ])
                            ->schema([
                                TextEntry::make('line_number')->label('Line')->numeric(),
                                TextEntry::make('item.item_code')->label('Item')->weight('medium'),
                                TextEntry::make('location.code')->label('Location'),
                                TextEntry::make('unitOfMeasure.uom_code')->label('UOM')->placeholder('Base'),
                                TextEntry::make('quantity')->numeric(decimalPlaces: 8),
                                TextEntry::make('quantity_base')->label('Base Qty')->numeric(decimalPlaces: 8),
                                TextEntry::make('unit_cost')->numeric(decimalPlaces: 8),
                                TextEntry::make('amount')->money('NGN'),
                                TextEntry::make('lot_number')->placeholder('—'),
                                TextEntry::make('serial_number')->placeholder('—'),
                                TextEntry::make('itemLedgerEntry.entry_number')->label('Item Ledger Entry')->placeholder('—'),
                            ]),
                    ]),

                Section::make('Totals')
                    ->columnSpan(6)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('total_quantity')
                            ->label('Total Base Quantity')
                            ->getStateUsing(fn (OpeningInventory $record): string => (string) $record->lines()->sum('quantity_base'))
                            ->numeric(decimalPlaces: 8),
                        TextEntry::make('total_value')
                            ->label('Total Value')
                            ->getStateUsing(fn (OpeningInventory $record): string => (string) $record->lines()->sum('amount'))
                            ->money('NGN'),
                    ]),

                Section::make('Ledger Traceability')
                    ->columnSpan(6)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('item_ledger_count')
                            ->label('Item Ledger Entries')
                            ->getStateUsing(fn (OpeningInventory $record): int => $record->itemLedgerEntries()->count())
                            ->badge(),
                        TextEntry::make('value_entry_count')
                            ->label('Value Entries')
                            ->getStateUsing(fn (OpeningInventory $record): int => ValueEntry::query()
                                ->whereIn('item_ledger_entry_no', $record->itemLedgerEntries()->select('entry_number'))
                                ->count())
                            ->badge(),
                        TextEntry::make('posting_transactions')
                            ->label('Posting Transactions')
                            ->getStateUsing(fn (OpeningInventory $record): string => ValueEntry::query()
                                ->whereIn('item_ledger_entry_no', $record->itemLedgerEntries()->select('entry_number'))
                                ->whereNotNull('posting_transaction_id')
                                ->distinct()
                                ->pluck('posting_transaction_id')
                                ->implode(', ') ?: '—')
                            ->columnSpanFull(),
                    ]),

                Section::make('System Details')
                    ->collapsed()
                    ->columnSpan(12)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]),
        ]);
    }
}
