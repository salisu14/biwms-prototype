<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Services\Finance\ProfitabilityReportService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ItemInfolist
{
    private static function activeBusinessId(): ?int
    {
        $value = session('active_business_id');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)->schema([
                    Section::make('Item Overview')
                        ->icon(Heroicon::OutlinedCube)
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->columnSpan([
                            'default' => 12,
                            'md' => 6,
                            'xl' => 5,
                        ])
                        ->schema([
                            TextEntry::make('item_code')
                                ->label('SKU / Number')
                                ->weight('bold')
                                ->size(TextSize::Large)
                                ->copyable(),
                            TextEntry::make('item_type')
                                ->badge()
                                ->formatStateUsing(fn (ItemType $state): string => $state->label())
                                ->color(fn (ItemType $state): string => $state->color())
                                ->icon(fn (ItemType $state): string => $state->icon()),
                            TextEntry::make('description')
                                ->columnSpan([
                                    'default' => 1,
                                    'md' => 2,
                                ])
                                ->size(TextSize::Large),
                        ]),

                    Section::make('Financials')
                        ->icon(Heroicon::OutlinedCurrencyDollar)
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])
                        ->columnSpan([
                            'default' => 12,
                            'md' => 6,
                            'xl' => 7,
                        ])
                        ->schema([
                            TextEntry::make('unit_price')
                                ->label('Selling Price')
                                ->money(fn ($record) => $record->currency?->code ?? 'NGN'),
                            TextEntry::make('indicative_margin_percent')
                                ->label('Indicative Gross Margin %')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemIndicativeMeasures($record)['indicative_margin_percent'])
                                ->formatStateUsing(fn ($state) => $state === null ? null : number_format((float) $state, 2).'%')
                                ->placeholder('N/A')
                                ->color(fn ($state) => $state === null ? 'gray' : ((float) $state >= 0 ? 'success' : 'danger')),
                            TextEntry::make('current_actual_inventory_cost')
                                ->label('Current Actual Inventory Cost (LCY)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemCostMeasures($record, self::activeBusinessId())['current_actual_inventory_cost'])
                                ->money('NGN')
                                ->placeholder('N/A'),
                            TextEntry::make('standard_cost')
                                ->label('Standard / Reference Cost')
                                ->money(fn ($record) => $record->currency?->code ?? 'NGN'),
                            TextEntry::make('markup_percent')
                                ->label('Indicative Markup %')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemIndicativeMeasures($record)['markup_percent'])
                                ->formatStateUsing(fn ($state) => $state === null ? null : number_format((float) $state, 2).'%')
                                ->placeholder('N/A')
                                ->color(fn ($state) => $state === null ? 'gray' : ((float) $state >= 0 ? 'success' : 'danger')),
                            TextEntry::make('last_actual_production_cost')
                                ->label('Last Actual Production Cost (LCY)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemCostMeasures($record, self::activeBusinessId())['last_actual_production_cost'])
                                ->money('NGN')
                                ->placeholder('N/A'),
                            TextEntry::make('indicative_unit_margin')
                                ->label('Indicative Unit Margin (Standard Cost)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemIndicativeMeasures($record)['indicative_unit_margin'])
                                ->money(fn ($record) => $record->currency?->code ?? 'NGN')
                                ->placeholder('N/A'),
                            TextEntry::make('inventory_method')
                                ->label('Inventory Method')
                                ->badge(),
                            TextEntry::make('average_actual_production_cost')
                                ->label('Average Actual Production Cost (LCY)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemCostMeasures($record, self::activeBusinessId())['average_actual_production_cost'])
                                ->money('NGN')
                                ->placeholder('N/A'),
                        ]),

                    Section::make('Stock Status')
                        ->icon(Heroicon::OutlinedCircleStack)
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])
                        ->columnSpan([
                            'default' => 12,
                            'md' => 6,
                            'xl' => 4,
                        ])
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

                    Section::make('Logistics')
                        ->icon(Heroicon::OutlinedTruck)
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 1,
                        ])
                        ->columnSpan([
                            'default' => 12,
                            'md' => 6,
                            'xl' => 3,
                        ])
                        ->schema([
                            TextEntry::make('uom.uom_code')
                                ->label('Base Unit of Measure'),
                            TextEntry::make('sku')
                                ->label('Default SKU / Variant')
                                ->placeholder('-'),
                            TextEntry::make('location.name')
                                ->label('Default Location')
                                ->placeholder('No Location Assigned'),
                        ]),

                    Section::make('Actual Performance (YTD)')
                        ->icon(Heroicon::OutlinedChartBar)
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])
                        ->columnSpan([
                            'default' => 12,
                            'md' => 12,
                            'xl' => 5,
                        ])
                        ->schema([
                            TextEntry::make('actual_quantity_sold')
                                ->label('Quantity Sold (Posted)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemActualPerformance($record, businessId: self::activeBusinessId())['quantity_sold'])
                                ->numeric(4)
                                ->placeholder('N/A'),
                            TextEntry::make('actual_net_revenue')
                                ->label('Net Sales (LCY)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemActualPerformance($record, businessId: self::activeBusinessId())['net_revenue'])
                                ->money('NGN')
                                ->placeholder('No posted sales'),
                            TextEntry::make('actual_cogs')
                                ->label('Actual COGS (LCY)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemActualPerformance($record, businessId: self::activeBusinessId())['actual_cogs'])
                                ->money('NGN')
                                ->placeholder('N/A'),
                            TextEntry::make('actual_gross_profit')
                                ->label('Realized Gross Profit (LCY)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemActualPerformance($record, businessId: self::activeBusinessId())['gross_profit'])
                                ->money('NGN')
                                ->placeholder('N/A'),
                            TextEntry::make('actual_gross_margin_percent')
                                ->label('Realized Gross Margin %')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemActualPerformance($record, businessId: self::activeBusinessId())['gross_margin_percent'])
                                ->formatStateUsing(fn ($state) => $state === null ? null : number_format((float) $state, 2).'%')
                                ->placeholder('N/A'),
                            TextEntry::make('average_selling_price')
                                ->label('Average Selling Price (LCY)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemActualPerformance($record, businessId: self::activeBusinessId())['average_selling_price'])
                                ->money('NGN')
                                ->placeholder('N/A'),
                            TextEntry::make('average_actual_cost')
                                ->label('Average Actual Cost (LCY)')
                                ->state(fn ($record) => app(ProfitabilityReportService::class)->itemActualPerformance($record, businessId: self::activeBusinessId())['average_actual_cost'])
                                ->money('NGN')
                                ->placeholder('N/A'),
                        ]),

                    Section::make('Manufacturing')
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 5,
                        ])
                        ->columnSpan([
                            'default' => 12,
                            'md' => 6,
                            'xl' => 6,
                        ])
                        ->visible(fn ($record) => $record?->item_type === ItemType::FINISHED_GOOD)
                        ->schema([
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

                    Section::make('Posting Groups & Configuration')
                        ->icon(Heroicon::OutlinedBuildingStorefront)
                        ->columns([
                            'default' => 1,
                            'md' => 3,
                        ])
                        ->columnSpan([
                            'default' => 12,
                            'md' => 6,
                            'xl' => 6,
                        ])
                        ->schema([
                            TextEntry::make('generalProductPostingGroup.code')
                                ->label('Gen. Prod. Posting Group'),
                            TextEntry::make('inventoryPostingGroup.code')
                                ->label('Inventory Posting Group'),
                            TextEntry::make('vat.code')
                                ->label('VAT Configuration'),
                        ]),

                    Section::make('Order History Snapshot')
                        ->icon(Heroicon::OutlinedClipboardDocumentList)
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->columnSpan([
                            'default' => 12,
                            'md' => 6,
                            'xl' => 7,
                        ])
                        ->schema([
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

                    Section::make('Restrictions')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->columns([
                            'default' => 1,
                            'md' => 3,
                        ])
                        ->columnSpan([
                            'default' => 12,
                            'md' => 6,
                            'xl' => 5,
                        ])
                        ->schema([
                            IconEntry::make('blocked')->boolean()->label('Fully Blocked'),
                            IconEntry::make('sales_blocked')->boolean()->label('Sales Blocked'),
                            IconEntry::make('purchasing_blocked')->boolean()->label('Purchasing Blocked'),
                        ]),

                    Section::make('System Information')
                        ->icon(Heroicon::OutlinedInformationCircle)
                        ->collapsed()
                        ->columns([
                            'default' => 1,
                            'md' => 2,
                        ])
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make('created_at')->dateTime(),
                            TextEntry::make('updated_at')->dateTime(),
                        ]),
                ]),
            ]);
    }
}
