<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class ItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Item Overview')
                    ->schema([
                        Grid::make(3)->schema([
                            Group::make([
                                TextEntry::make('item_code')
                                    ->label('SKU / Number')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('description')
                                    ->size(TextSize::Large),
                            ])->columnSpan(2),

                            TextEntry::make('item_type')
                                ->badge()
                                ->formatStateUsing(fn (ItemType $state): string => $state->label())
                                ->color(fn (ItemType $state): string => $state->color())
                                ->icon(fn (ItemType $state): string => $state->icon()),
                        ]),
                    ]),

                Grid::make(3)->schema([
                    Section::make('Financials')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('unit_price')
                                ->label('Selling Price')
                                ->money(fn ($record) => $record->currency?->code ?? 'NGN'),
                            TextEntry::make('standard_cost')
                                ->label('Standard Cost')
                                ->money(fn ($record) => $record->currency?->code ?? 'NGN'),
                            TextEntry::make('inventory_method')
                                ->label('Inventory Method')
                                ->badge(),
                        ]),

                    Section::make('Logistics')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('uom.uom_code')
                                ->label('Base Unit of Measure'),
                            TextEntry::make('sku.sku_code')
                                ->label('Default SKU/Variant'),
                            TextEntry::make('location.name')
                                ->label('Default Location')
                                ->placeholder('No Location Assigned'),
                        ]),

                    Section::make('Stock Status')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('ledger_on_hand')
                                ->label('On Hand')
                                ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.$record->base_unit_of_measure)
                                ->weight('bold')
                                ->color(fn ($state) => $state <= 0 ? 'danger' : 'success'),
                            TextEntry::make('qty_on_sales_order')
                                ->label('Qty on Sales Order')
                                ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.$record->base_unit_of_measure)
                                ->color('warning'),
                            TextEntry::make('qty_on_purchase_order')
                                ->label('Qty on Purchase Order')
                                ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.$record->base_unit_of_measure)
                                ->color('info'),
                            TextEntry::make('available_to_promise')
                                ->label('Available to Promise')
                                ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.$record->base_unit_of_measure)
                                ->weight('bold')
                                ->color(fn ($state) => $state <= 0 ? 'danger' : 'success'),
                            TextEntry::make('projected_available')
                                ->label('Projected Available (After PO)')
                                ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.$record->base_unit_of_measure)
                                ->color(fn ($state) => $state <= 0 ? 'danger' : 'success'),
                            TextEntry::make('stock_alert')
                                ->label('Stock Alert')
                                ->state(function ($record): string {
                                    if ((float) $record->available_to_promise <= 0) {
                                        return 'Sold Out';
                                    }

                                    if ((bool) $record->needs_reorder) {
                                        return 'Reorder Needed';
                                    }

                                    return 'In Stock';
                                })
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Sold Out' => 'danger',
                                    'Reorder Needed' => 'warning',
                                    default => 'success',
                                }),
                            TextEntry::make('reorder_point')
                                ->label('Reorder Point'),
                            TextEntry::make('bin_code')
                                ->label('Bin Code'),
                        ]),
                ]),

                Section::make('Order History Snapshot')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('recent_sales_orders')
                                ->label('Recent Sales Orders')
                                ->state(function ($record): string {
                                    $lines = $record->salesOrderLines()
                                        ->with('salesOrder')
                                        ->whereHas('salesOrder')
                                        ->latest('id')
                                        ->limit(5)
                                        ->get();

                                    if ($lines->isEmpty()) {
                                        return 'No sales order history for this item.';
                                    }

                                    return $lines->map(function ($line): string {
                                        $orderNo = $line->salesOrder?->order_number ?? 'N/A';
                                        $status = $line->salesOrder?->status?->value ?? 'N/A';

                                        return "{$orderNo} | Qty: {$line->quantity} | Shipped: {$line->quantity_shipped} | {$status}";
                                    })->implode("\n");
                                })
                                ->prose(),
                            TextEntry::make('recent_purchase_orders')
                                ->label('Recent Purchase Orders')
                                ->state(function ($record): string {
                                    $lines = $record->purchaseOrderLines()
                                        ->with('purchaseOrder')
                                        ->whereHas('purchaseOrder')
                                        ->latest('id')
                                        ->limit(5)
                                        ->get();

                                    if ($lines->isEmpty()) {
                                        return 'No purchase order history for this item.';
                                    }

                                    return $lines->map(function ($line): string {
                                        $orderNo = $line->purchaseOrder?->order_number ?? 'N/A';
                                        $status = $line->purchaseOrder?->status?->value ?? 'N/A';

                                        return "{$orderNo} | Qty: {$line->quantity} | Received: {$line->received_quantity} | {$status}";
                                    })->implode("\n");
                                })
                                ->prose(),
                        ]),
                    ]),

                Section::make('Posting Groups & Configuration')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('generalProductPostingGroup.code')
                                ->label('Gen. Prod. Posting Group'),
                            TextEntry::make('inventoryPostingGroup.code')
                                ->label('Inventory Posting Group'),
                            TextEntry::make('vat.code')
                                ->label('VAT Configuration'),
                        ]),
                    ]),

                Section::make('Manufacturing')
                    ->visible(fn ($record) => $record?->item_type === ItemType::FINISHED_GOOD)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('productionBom.code')
                                ->label('Production BOM')
                                ->placeholder('Not set'),
                            TextEntry::make('routing.code')
                                ->label('Routing')
                                ->placeholder('Not set'),
                            TextEntry::make('total_produced_cartons')
                                ->label('Total Produced (Cartons)')
                                ->state(fn ($record): float => (float) $record->ledgerEntries()
                                    ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                                    ->sum('quantity'))
                                ->numeric(4)
                                ->weight('bold')
                                ->color('success'),
                            TextEntry::make('total_produced_packs')
                                ->label('Total Produced (Packs)')
                                ->state(function ($record): float {
                                    $producedCartons = (float) $record->ledgerEntries()
                                        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                                        ->sum('quantity');

                                    return $producedCartons * 24;
                                })
                                ->numeric(4)
                                ->color('info'),
                            TextEntry::make('total_produced_pieces')
                                ->label('Total Produced (Pieces)')
                                ->state(function ($record): float {
                                    $producedCartons = (float) $record->ledgerEntries()
                                        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                                        ->sum('quantity');

                                    return $producedCartons * 288;
                                })
                                ->numeric(4)
                                ->color('primary'),
                        ]),
                    ]),

                Section::make('Restrictions')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            IconEntry::make('blocked')->boolean()->label('Fully Blocked'),
                            IconEntry::make('sales_blocked')->boolean()->label('Sales Blocked'),
                            IconEntry::make('purchasing_blocked')->boolean()->label('Purchasing Blocked'),
                        ]),
                    ]),

                Section::make('System Information')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')->dateTime(),
                            TextEntry::make('updated_at')->dateTime(),
                        ]),
                    ]),
            ]);
    }
}
