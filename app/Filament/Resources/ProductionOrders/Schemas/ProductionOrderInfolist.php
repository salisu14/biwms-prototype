<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Services\Manufacturing\MultiLevelProductionReadinessService;
use App\Services\Manufacturing\ProductionCostSummaryService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class ProductionOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->icon('heroicon-m-document-text')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('document_number')
                            ->label('Order No.')
                            ->weight(FontWeight::Bold)
                            ->copyable(),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (ProductionOrderStatus $state): string => match ($state) {
                                ProductionOrderStatus::SIMULATED => 'gray',
                                ProductionOrderStatus::PLANNED => 'info',
                                ProductionOrderStatus::FIRM_PLANNED => 'warning',
                                ProductionOrderStatus::RELEASED => 'success',
                                ProductionOrderStatus::FINISHED => 'primary',
                                default => 'gray',
                            }),

                        TextEntry::make('source_type')
                            ->label('Source Type')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('source_no')
                            ->label('Source No.')
                            ->placeholder('N/A'),

                        TextEntry::make('capexProject.project_number')
                            ->label('CapEx Project')
                            ->placeholder('None (Operational)')
                            ->color('info')
                            ->icon('heroicon-m-building-office'),

                        TextEntry::make('item.description')
                            ->label('Item')
                            ->columnSpan(2)
                            ->weight(FontWeight::Bold),

                        TextEntry::make('description')
                            ->label('Order Description')
                            ->columnSpanFull(),
                    ]),

                Grid::make(2)->schema([
                    Section::make('Quantities & Progress')
                        ->icon('heroicon-m-beaker')
                        ->columnSpan(1)
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('quantity')
                                    ->numeric(4)
                                    ->suffix(fn ($record) => " {$record->unit_of_measure_code}"),

                                TextEntry::make('quantity_base')
                                    ->label('Base Quantity')
                                    ->numeric(4),

                                TextEntry::make('remaining_quantity')
                                    ->label('Remaining to Produce')
                                    ->numeric(4)
                                    ->color('warning')
                                    ->weight(FontWeight::Bold),

                                IconEntry::make('posted')
                                    ->label('Posted Status')
                                    ->boolean(),

                                TextEntry::make('produced_quantity')
                                    ->label('Produced Quantity')
                                    ->state(function ($record): float {
                                        $produced = (float) $record->itemLedgerEntries()
                                            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                                            ->where('item_id', $record->item_id)
                                            ->sum('quantity');

                                        $uomCode = (string) ($record->unit_of_measure_code ?? '');
                                        if (! $record->item_id || $uomCode === '') {
                                            return $produced;
                                        }

                                        $item = $record->item;
                                        if (! $item) {
                                            return $produced;
                                        }

                                        $baseUom = (string) ($item->base_unit_of_measure ?? '');
                                        if ($baseUom !== '' && strtoupper($uomCode) === strtoupper($baseUom)) {
                                            return $produced;
                                        }

                                        $assignment = $item->uoms()->where('uom_code', $uomCode)->first();
                                        $factor = (float) ($assignment?->pivot?->conversion_factor ?? 1);
                                        if ($factor <= 0) {
                                            return $produced;
                                        }

                                        return $produced / $factor;
                                    })
                                    ->numeric(4)
                                    ->suffix(fn ($record): string => ' '.($record->unit_of_measure_code ?? 'PCS'))
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),
                            ]),
                        ]),

                    Section::make('Timeline')
                        ->icon('heroicon-m-calendar-days')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('due_date')
                                ->date()
                                ->color('danger'),

                            Grid::make(2)->schema([
                                TextEntry::make('starting_date_time')
                                    ->label('Scheduled Start')
                                    ->dateTime(),
                                TextEntry::make('ending_date_time')
                                    ->label('Scheduled End')
                                    ->dateTime(),
                            ]),
                        ]),
                ]),

                Section::make('BOM & Routing Structure')
                    ->icon('heroicon-m-wrench-screwdriver')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('productionBom.description')
                            ->label('BOM')
                            ->hint(fn ($record) => $record->production_bom_version_id ? "Version: {$record->productionBomVersion?->version_code}" : 'Latest'),

                        TextEntry::make('routing.description')
                            ->label('Routing')
                            ->hint(fn ($record) => $record->routing_version_id ? "Version: {$record->routingVersion?->version_code}" : 'Latest'),

                        TextEntry::make('flushing_method')
                            ->badge(),

                        TextEntry::make('scrap_percent')
                            ->label('Scrap Factor')
                            ->numeric(2)
                            ->suffix('%'),
                    ]),

                Section::make('Warehouse & Costing')
                    ->icon('heroicon-m-banknotes')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('location_code')
                            ->label('Target Location')
                            ->icon('heroicon-m-map-pin'),

                        TextEntry::make('costing_method')
                            ->label('Method'),

                        TextEntry::make('unit_cost')
                            ->money('NGN'),

                        TextEntry::make('inventoryPostingGroup.code')
                            ->label('Inv. Posting Group'),

                        TextEntry::make('generalProductPostingGroup.code')
                            ->label('Gen. Prod. Posting Group'),

                        TextEntry::make('total_actual_cost')
                            ->label('Total Actual Cost')
                            ->money('NGN')
                            ->weight(FontWeight::Bold)
                            ->color('success'),

                        TextEntry::make('actual_cost_per_unit')
                            ->label('Actual Cost / Unit (FG)')
                            ->suffix(fn ($record): string => ' / '.($record->unit_of_measure_code ?? 'Unit'))
                            ->state(function ($record): ?float {
                                // 1. Get actual produced qty in BASE UoM from ledger
                                $producedBaseQty = (float) $record->itemLedgerEntries()
                                    ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                                    ->where('item_id', $record->item_id)
                                    ->sum('quantity');

                                if ($producedBaseQty <= 0) {
                                    return null;
                                }

                                // 2. Convert to ORDER UoM
                                $uomCode = (string) ($record->unit_of_measure_code ?? '');
                                $item = $record->item;

                                if ($item && $uomCode !== '') {
                                    $baseUom = (string) ($item->base_unit_of_measure ?? '');
                                    if ($baseUom !== '' && strtoupper($uomCode) !== strtoupper($baseUom)) {
                                        $assignment = $item->uoms()->where('uom_code', $uomCode)->first();
                                        $factor = (float) ($assignment?->pivot?->conversion_factor ?? 1);
                                        if ($factor > 0) {
                                            $producedBaseQty = $producedBaseQty / $factor;
                                        }
                                    }
                                }

                                // 3. Now $producedBaseQty is actually the produced qty in ORDER UoM
                                return (float) $record->total_actual_cost / $producedBaseQty;
                            })
                            ->money('NGN')
                            ->weight(FontWeight::Bold)
                            ->color('info')
                            ->placeholder('N/A'),

                        TextEntry::make('actual_cost_per_piece')
                            ->label('Actual Cost / Piece')
                            ->state(function ($record): ?float {
                                // Always divide by actual base-qty from ledger — no planned values
                                $producedBaseQty = (float) $record->itemLedgerEntries()
                                    ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                                    ->where('item_id', $record->item_id)
                                    ->sum('quantity');

                                if ($producedBaseQty <= 0) {
                                    return null;
                                }

                                return (float) $record->total_actual_cost / $producedBaseQty;
                            })
                            ->money('NGN')
                            ->weight(FontWeight::Bold)
                            ->color('success')
                            ->placeholder('N/A'),
                    ]),

                Section::make('Production Costing')
                    ->icon('heroicon-m-scale')
                    ->visible(fn ($record): bool => auth()->user()?->can('viewProductionCost', $record) ?? false)
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ])
                    ->schema([
                        TextEntry::make('expected_material_cost')
                            ->label('Expected Material')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['expected_material_cost'])
                            ->money('NGN'),
                        TextEntry::make('actual_material_cost')
                            ->label('Actual Material')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['actual_material_cost'])
                            ->money('NGN'),
                        TextEntry::make('material_price_variance')
                            ->label('Material Price Variance')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['material_price_variance'])
                            ->money('NGN')
                            ->color(fn ($state): string => abs((float) $state) > 0.01 ? 'warning' : 'success'),
                        TextEntry::make('material_quantity_variance')
                            ->label('Material Qty Variance')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['material_quantity_variance'])
                            ->money('NGN')
                            ->color(fn ($state): string => abs((float) $state) > 0.01 ? 'warning' : 'success'),
                        TextEntry::make('expected_capacity_cost')
                            ->label('Expected Capacity')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['expected_capacity_cost'])
                            ->money('NGN'),
                        TextEntry::make('actual_capacity_cost')
                            ->label('Actual Capacity')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['actual_capacity_cost'])
                            ->money('NGN'),
                        TextEntry::make('capacity_rate_variance')
                            ->label('Capacity Rate Variance')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['capacity_rate_variance'])
                            ->money('NGN')
                            ->color(fn ($state): string => abs((float) $state) > 0.01 ? 'warning' : 'success'),
                        TextEntry::make('capacity_efficiency_variance')
                            ->label('Capacity Efficiency Variance')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['capacity_efficiency_variance'])
                            ->money('NGN')
                            ->color(fn ($state): string => abs((float) $state) > 0.01 ? 'warning' : 'success'),
                        TextEntry::make('expected_overhead_cost')
                            ->label('Expected Overhead')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['expected_overhead_cost'])
                            ->money('NGN'),
                        TextEntry::make('actual_overhead_cost')
                            ->label('Actual Overhead')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['actual_overhead_cost'])
                            ->money('NGN'),
                        TextEntry::make('capacity_overhead_variance')
                            ->label('Overhead Variance')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['capacity_overhead_variance'])
                            ->money('NGN')
                            ->color(fn ($state): string => abs((float) $state) > 0.01 ? 'warning' : 'success'),
                        TextEntry::make('total_production_variance')
                            ->label('Total Variance')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['total_production_variance'])
                            ->money('NGN')
                            ->weight(FontWeight::Bold)
                            ->color(fn ($state): string => abs((float) $state) > 0.01 ? 'warning' : 'success'),
                        TextEntry::make('expected_output_cost')
                            ->label('Expected Output')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['expected_output_cost'])
                            ->money('NGN'),
                        TextEntry::make('actual_output_cost')
                            ->label('Actual Output')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['actual_output_cost'])
                            ->money('NGN'),
                        TextEntry::make('allocated_output_cost')
                            ->label('Allocated Cost')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['allocated_output_cost'])
                            ->money('NGN'),
                        TextEntry::make('unallocated_cost')
                            ->label('Unallocated Cost')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['unallocated_cost'])
                            ->money('NGN')
                            ->color(fn ($state): string => abs((float) $state) > 0.01 ? 'warning' : 'success'),
                        TextEntry::make('uncleared_expected_cost')
                            ->label('Uncleared Expected')
                            ->state(fn ($record): float => (float) app(ProductionCostSummaryService::class)->summarize($record)['uncleared_expected_cost'])
                            ->money('NGN')
                            ->color(fn ($state): string => abs((float) $state) > 0.01 ? 'warning' : 'success'),
                        TextEntry::make('cost_settlement_status')
                            ->label('Settlement Status')
                            ->badge(),
                        TextEntry::make('cost_settlement_classification')
                            ->label('Settlement Classification')
                            ->badge(),
                    ]),

                Section::make('Multi-Level Execution')
                    ->icon('heroicon-m-squares-plus')
                    ->visible(fn ($record): bool => $record->productionHierarchies()->exists()
                        || $record->supplyLinksAsParent()->exists()
                        || $record->supplyLinksAsChild()->exists())
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ])
                    ->schema([
                        TextEntry::make('production_hierarchy_status')
                            ->label('Hierarchy Status')
                            ->state(fn ($record): string => (string) ($record->productionHierarchy?->status?->value ?? $record->rootProductionOrder?->productionHierarchy?->status?->value ?? 'not planned'))
                            ->badge(),
                        TextEntry::make('child_orders_count')
                            ->label('Child Orders')
                            ->state(fn ($record): int => $record->childProductionOrders()->count())
                            ->badge()
                            ->color('info'),
                        TextEntry::make('child_supply_required')
                            ->label('Required Supply')
                            ->state(fn ($record): float => (float) $record->supplyLinksAsParent()->sum('required_quantity_base') + (float) $record->supplyLinksAsChild()->sum('required_quantity_base'))
                            ->numeric(4),
                        TextEntry::make('child_supply_supplied')
                            ->label('Supplied')
                            ->state(fn ($record): float => (float) $record->supplyLinksAsParent()->sum('supplied_quantity_base') + (float) $record->supplyLinksAsChild()->sum('supplied_quantity_base'))
                            ->numeric(4)
                            ->color('success'),
                        TextEntry::make('child_supply_consumed')
                            ->label('Consumed')
                            ->state(fn ($record): float => (float) $record->supplyLinksAsParent()->sum('consumed_quantity_base') + (float) $record->supplyLinksAsChild()->sum('consumed_quantity_base'))
                            ->numeric(4)
                            ->color('warning'),
                        TextEntry::make('generated_from_parent')
                            ->label('Generated From')
                            ->state(fn ($record): string => $record->parentProductionOrder?->document_number ?? 'Root / standalone')
                            ->placeholder('Root / standalone'),
                        TextEntry::make('source_component_line')
                            ->label('Supplying Component')
                            ->state(fn ($record): string => $record->sourceProductionOrderComponent?->line_number
                                ? '#'.$record->sourceProductionOrderComponent->line_number.' '.$record->sourceProductionOrderComponent?->item?->item_code
                                : '—'),
                        TextEntry::make('hierarchy_blocking_reason')
                            ->label('Blocking Reason')
                            ->state(function ($record): string {
                                $readiness = app(MultiLevelProductionReadinessService::class)->completionReadiness($record);

                                return $readiness['ready']
                                    ? 'Ready'
                                    : (string) ($readiness['reasons'][0]['message'] ?? 'Review hierarchy demand');
                            })
                            ->color(fn (string $state): string => $state === 'Ready' ? 'success' : 'warning')
                            ->columnSpanFull(),
                    ]),

                Section::make('Audit & Tracking')
                    ->icon('heroicon-m-user-circle')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('creator.name')->label('Created By'),
                        TextEntry::make('created_at')->dateTime(),

                        TextEntry::make('poster.name')->label('Posted By')->placeholder('-'),
                        TextEntry::make('posted_at')->dateTime()->placeholder('-'),

                        TextEntry::make('finisher.name')->label('Finished By')->placeholder('-'),
                        TextEntry::make('finished_at')->dateTime()->placeholder('-'),

                        IconEntry::make('reserved_from_stock')
                            ->label('Reserved')
                            ->boolean(),

                        TextEntry::make('priority')
                            ->numeric(),
                    ]),

                Section::make('Related Warehouse Documents')
                    ->icon('heroicon-m-truck')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('warehouseRequests_count')
                            ->label('Warehouse Requests')
                            ->state(fn ($record) => $record->warehouseRequests()->count())
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-m-clipboard-document-list'),

                        TextEntry::make('warehouseActivities_count')
                            ->label('Warehouse Activities (Picks/Put-aways)')
                            ->state(fn ($record) => $record->warehouseActivities()->count())
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-m-arrow-path'),
                    ])
                    ->visible(fn ($record) => $record->status === ProductionOrderStatus::RELEASED),
            ]);
    }
}
