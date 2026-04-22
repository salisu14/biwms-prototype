<?php

declare(strict_types=1);

namespace App\Filament\Pages\Finance;

use App\Models\Manufacturing\ProductionOrder;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Yield Report — BC "Production Order – Yield Report" equivalent.
 *
 * For each production order, shows:
 *
 *   Production Order  | Production Date | Item No | Item Description
 *   Standard Consumption (from BOM qty × order qty)
 *   Actual Consumption  (from production_order_components.actual_quantity_consumed)
 *   Consumption Variance (actual - standard, positive = over-consumed)
 *   Standard Output     (production_order.quantity)
 *   Actual Output       (from item_ledger_entries type=output for this prod order)
 *   Output Variance     (standard - actual, positive = shortfall)
 *   Yield %             (actual_output / standard_output × 100)
 *
 * Data sources:
 *   - production_orders           → order header, standard output qty, item
 *   - production_order_components → std/actual consumption per component
 *   - item_ledger_entries         → actual output posted (entry_type = 'Output')
 */
class YieldReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.finance.yield-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Yield Report';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $title = 'Yield Report';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductionOrder::query()
                    ->with(['item'])
                    ->select('production_orders.*')
                    // ── Standard Consumption ─────────────────────────────────────
                    // Sum of all component expected_quantity (BOM qty × order qty)
                    ->selectRaw(
                        '(SELECT COALESCE(SUM(poc.expected_quantity), 0)
                           FROM production_order_components poc
                          WHERE poc.production_order_id = production_orders.id
                         ) AS std_consumption'
                    )
                    // ── Actual Consumption ────────────────────────────────────────
                    // Sum of what was actually consumed (posted via Production Journal)
                    ->selectRaw(
                        '(SELECT COALESCE(SUM(poc.actual_quantity_consumed), 0)
                           FROM production_order_components poc
                          WHERE poc.production_order_id = production_orders.id
                         ) AS actual_consumption'
                    )
                    // ── Actual Output ─────────────────────────────────────────────
                    // Item Ledger Entries with entry_type = 'Output' for this order
                    // ILE links to production order via source_type + source_id
                    ->selectRaw(
                        "(SELECT COALESCE(SUM(ile.quantity), 0)
                           FROM item_ledger_entries ile
                          WHERE ile.source_type = 'ProductionOrder'
                            AND ile.source_id = production_orders.id
                            AND ile.entry_type = 'Output'
                         ) AS actual_output"
                    )
            )
            ->columns([
                TextColumn::make('document_number')
                    ->label('Production Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn ($record) => route('filament.admin.resources.production-orders.view', $record)),

                TextColumn::make('starting_date_time')
                    ->label('Production Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('item.no')
                    ->label('Item No.')
                    ->searchable(),

                TextColumn::make('item.description')
                    ->label('Item Description')
                    ->limit(30)
                    ->searchable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'released' => 'warning',
                        'in_progress' => 'info',
                        'finished' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                // ── CONSUMPTION ──────────────────────────────────────────────────

                TextColumn::make('std_consumption')
                    ->label('Std Consumption')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->color('gray')
                    ->summarize(Sum::make()->label('Total Std'))
                    ->extraHeaderAttributes(['style' => 'border-left: 2px solid #e5e7eb;'])
                    ->extraCellAttributes(['style' => 'border-left: 2px solid #e5e7eb;']),

                TextColumn::make('actual_consumption')
                    ->label('Actual Consumption')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->summarize(Sum::make()->label('Total Actual')),

                TextColumn::make('consumption_variance')
                    ->label('Consump. Variance')
                    ->state(fn ($record) => (float) $record->actual_consumption - (float) $record->std_consumption)
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : null))
                    ->description(fn ($record) => $record->std_consumption > 0
                        ? sprintf('%+.1f%%', (((float) $record->actual_consumption - (float) $record->std_consumption) / (float) $record->std_consumption) * 100)
                        : null
                    )
                    ->summarize(Sum::make()->label('Net Variance')),

                // ── OUTPUT ───────────────────────────────────────────────────────

                TextColumn::make('quantity')
                    ->label('Std Output')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->color('gray')
                    ->summarize(Sum::make()->label('Total Std'))
                    ->extraHeaderAttributes(['style' => 'border-left: 2px solid #e5e7eb;'])
                    ->extraCellAttributes(['style' => 'border-left: 2px solid #e5e7eb;']),

                TextColumn::make('actual_output')
                    ->label('Actual Output')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->summarize(Sum::make()->label('Total Actual')),

                TextColumn::make('output_variance')
                    ->label('Output Variance')
                    ->state(fn ($record) => (float) $record->quantity - (float) $record->actual_output)
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'info' : null))
                    ->summarize(Sum::make()->label('Net Shortfall')),

                TextColumn::make('yield_pct')
                    ->label('Yield %')
                    ->state(fn ($record) => $record->quantity > 0
                        ? round(((float) $record->actual_output / (float) $record->quantity) * 100, 1)
                        : 0
                    )
                    ->numeric(decimalPlaces: 1)
                    ->suffix('%')
                    ->alignment('right')
                    ->color(fn ($state) => match (true) {
                        $state >= 98 => 'success',
                        $state >= 90 => 'warning',
                        default => 'danger',
                    })
                    ->weight(fn ($state) => $state < 90 ? 'bold' : null),

                TextColumn::make('unit_of_measure_code')
                    ->label('UOM')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starting_date_time', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'simulated' => 'Simulated',
                        'planned' => 'Planned',
                        'firm_planned' => 'Firm Planned',
                        'released' => 'Released',
                        'in_progress' => 'In Progress',
                        'finished' => 'Finished',
                        'closed' => 'Closed',
                    ])
                    ->native(false),

                Filter::make('date_range')
                    ->label('Production Date Range')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->native(false)
                            ->default(now()->startOfMonth()),
                        DatePicker::make('to')
                            ->label('To')
                            ->native(false)
                            ->default(now()),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn ($q) => $q->whereDate('starting_date_time', '>=', $data['from']))
                        ->when($data['to'], fn ($q) => $q->whereDate('starting_date_time', '<=', $data['to']))
                    ),

                Filter::make('low_yield')
                    ->label('Low Yield Only (< 90%)')
                    ->query(fn (Builder $query) => $query->whereRaw(
                        "(SELECT COALESCE(SUM(ile.quantity), 0)
                           FROM item_ledger_entries ile
                          WHERE ile.source_type = 'ProductionOrder'
                            AND ile.source_id = production_orders.id
                            AND ile.entry_type = 'Output'
                         ) < production_orders.quantity * 0.9"
                    )),
            ])
            ->striped()
            ->paginated([25, 50, 100, 'all']);
    }
}
