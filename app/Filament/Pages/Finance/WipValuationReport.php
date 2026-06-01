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
 * WIP Valuation Report — BC "Inventory Valuation – WIP" report equivalent.
 *
 * Shows the value of production orders that are partially complete
 * (status = Released | In Progress) at a point in time, broken down into:
 *
 *   Expected Cost:  costs posted as "expected" (material issued, but order not finished)
 *   Actual Cost:    costs posted as "actual"   (output posted)
 *   WIP Balance:    expected - actual (the un-absorbed manufacturing cost still on WIP)
 *   Variance:       cost_amount_actual - standard cost (positive = over-absorbed)
 *
 * Data source: value_entries joined to production_orders.
 */
class WipValuationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.finance.wip-valuation-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'WIP Valuation';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $title = 'WIP Valuation Report';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductionOrder::query()
                    ->with(['item', 'lines'])
                    ->whereIn('status', ['PLANNED', 'FIRM_PLANNED', 'RELEASED', 'FINISHED'])
                    ->select('production_orders.*')
                    ->selectRaw('coalesce(production_orders.starting_date_time, production_orders.ending_date_time, production_orders.finished_at, production_orders.created_at) as production_date_ref')
                    ->selectRaw(
                        '(EXISTS (
                            SELECT 1
                            FROM value_entries ve_exist
                            WHERE ve_exist.production_order_no = production_orders.document_number
                        )) as has_value_entries'
                    )
                    ->selectRaw(
                        '(SELECT
                            CASE
                                WHEN EXISTS (
                                    SELECT 1
                                    FROM value_entries ve_exist
                                    WHERE ve_exist.production_order_no = production_orders.document_number
                                )
                                THEN COALESCE(SUM(ve.cost_amount_expected), 0)
                                ELSE (
                                    SELECT COALESCE(SUM(ile_expected.cost_amount_expected), 0)
                                    FROM item_ledger_entries ile_expected
                                    WHERE ile_expected.source_type = \'App\\\\Models\\\\Manufacturing\\\\ProductionOrder\'
                                      AND ile_expected.source_id = production_orders.id
                                )
                            END
                          FROM value_entries ve
                          WHERE ve.production_order_no = production_orders.document_number
                         ) as expected_cost_total'
                    )
                    ->selectRaw(
                        '(SELECT
                            CASE
                                WHEN EXISTS (
                                    SELECT 1
                                    FROM value_entries ve_exist
                                    WHERE ve_exist.production_order_no = production_orders.document_number
                                )
                                THEN COALESCE(SUM(ve.cost_amount_actual), 0)
                                ELSE (
                                    SELECT COALESCE(SUM(ile_actual.cost_amount_actual), 0)
                                    FROM item_ledger_entries ile_actual
                                    WHERE ile_actual.source_type = \'App\\\\Models\\\\Manufacturing\\\\ProductionOrder\'
                                      AND ile_actual.source_id = production_orders.id
                                )
                            END
                          FROM value_entries ve
                          WHERE ve.production_order_no = production_orders.document_number
                         ) as actual_cost_total'
                    )
                    ->selectRaw(
                        '(SELECT
                            CASE
                                WHEN EXISTS (
                                    SELECT 1
                                    FROM value_entries ve_exist
                                    WHERE ve_exist.production_order_no = production_orders.document_number
                                )
                                THEN COALESCE(SUM(ve.variance_amount), 0)
                                ELSE (
                                    (
                                        SELECT COALESCE(SUM(ile_actual.cost_amount_actual), 0)
                                        FROM item_ledger_entries ile_actual
                                        WHERE ile_actual.source_type = \'App\\\\Models\\\\Manufacturing\\\\ProductionOrder\'
                                          AND ile_actual.source_id = production_orders.id
                                    ) - (
                                        SELECT COALESCE(SUM(ile_expected.cost_amount_expected), 0)
                                        FROM item_ledger_entries ile_expected
                                        WHERE ile_expected.source_type = \'App\\\\Models\\\\Manufacturing\\\\ProductionOrder\'
                                          AND ile_expected.source_id = production_orders.id
                                    )
                                )
                            END
                          FROM value_entries ve
                          WHERE ve.production_order_no = production_orders.document_number
                         ) as total_variance'
                    )
            )
            ->columns([
                TextColumn::make('document_number')
                    ->label('Production Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn ($record) => route('filament.admin.resources.production-orders.view', $record)),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'RELEASED' => 'warning',
                        'FINISHED' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('item.description')
                    ->label('Item')
                    ->limit(25)
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Order Qty')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right'),

                TextColumn::make('unit_of_measure_code')
                    ->label('UOM'),

                TextColumn::make('production_date_ref')
                    ->label('Production Date')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('data_source')
                    ->label('Data Source')
                    ->state(fn ($record) => (bool) ($record->has_value_entries ?? false) ? 'Value Entry' : 'Item Ledger Fallback')
                    ->badge()
                    ->color(fn ($state) => $state === 'Value Entry' ? 'success' : 'warning')
                    ->toggleable(),

                TextColumn::make('expected_cost_total')
                    ->label('Expected Cost')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->alignment('right')
                    ->color('warning')
                    ->summarize(Sum::make()->label('Total Expected')),

                TextColumn::make('actual_cost_total')
                    ->label('Actual Cost')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->alignment('right')
                    ->summarize(Sum::make()->label('Total Actual')),

                TextColumn::make('wip_balance')
                    ->label('WIP Balance')
                    ->state(fn ($record) => (float) $record->expected_cost_total - (float) $record->actual_cost_total)
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->alignment('right')
                    ->color(fn ($state) => $state > 0 ? 'warning' : ($state < 0 ? 'danger' : 'success'))
                    ->summarize(Sum::make()->label('Net WIP')),

                TextColumn::make('total_variance')
                    ->label('Variance')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->alignment('right')
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : null))
                    ->summarize(Sum::make()->label('Total Variance'))
                    ->toggleable(),

                TextColumn::make('unit_cost')
                    ->label('Std Unit Cost')
                    ->numeric(decimalPlaces: 4)
                    ->prefix('$')
                    ->alignment('right')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starting_date_time', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'PLANNED' => 'Planned',
                        'FIRM_PLANNED' => 'Firm Planned',
                        'RELEASED' => 'Released',
                        'FINISHED' => 'Finished',
                    ])
                    ->native(false),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label('Starting Date From')->native(false),
                        DatePicker::make('to')->label('Starting Date To')->native(false),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereRaw('date(coalesce(production_orders.starting_date_time, production_orders.ending_date_time, production_orders.finished_at, production_orders.created_at)) >= ?', [$data['from']]))
                            ->when($data['to'], fn ($q) => $q->whereRaw('date(coalesce(production_orders.starting_date_time, production_orders.ending_date_time, production_orders.finished_at, production_orders.created_at)) <= ?', [$data['to']]));
                    }),
            ])
            ->defaultSort('production_date_ref', 'desc')
            ->striped()
            ->paginated([25, 50, 100]);
    }
}
