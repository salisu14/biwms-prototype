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

    // Filter state
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $status = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductionOrder::query()
                    ->with(['item', 'lines'])
                    ->whereIn('status', ['released', 'in_progress', 'finished'])
                    ->when($this->dateFrom, fn ($q) => $q->whereDate('starting_date_time', '>=', $this->dateFrom))
                    ->when($this->dateTo, fn ($q) => $q->whereDate('starting_date_time', '<=', $this->dateTo))
                    ->when($this->status, fn ($q) => $q->where('status', $this->status))
                    ->select('production_orders.*')
                    ->selectRaw(
                        '(SELECT COALESCE(SUM(cost_amount_expected), 0)
                           FROM value_entries
                          WHERE production_order_no = production_orders.document_number
                         ) as expected_cost_total'
                    )
                    ->selectRaw(
                        '(SELECT COALESCE(SUM(cost_amount_actual), 0)
                           FROM value_entries
                          WHERE production_order_no = production_orders.document_number
                         ) as actual_cost_total'
                    )
                    ->selectRaw(
                        '(SELECT COALESCE(SUM(variance_amount), 0)
                           FROM value_entries
                          WHERE production_order_no = production_orders.document_number
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
                        'released' => 'warning',
                        'in_progress' => 'info',
                        'finished' => 'success',
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

                TextColumn::make('starting_date_time')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

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
                        'released' => 'Released',
                        'in_progress' => 'In Progress',
                        'finished' => 'Finished',
                    ])
                    ->native(false),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label('Starting Date From')->native(false),
                        DatePicker::make('to')->label('Starting Date To')->native(false),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('starting_date_time', '>=', $data['from']))
                            ->when($data['to'], fn ($q) => $q->whereDate('starting_date_time', '<=', $data['to']));
                    }),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }
}
