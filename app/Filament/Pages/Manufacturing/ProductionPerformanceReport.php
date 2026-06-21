<?php

namespace App\Filament\Pages\Manufacturing;

use App\Models\Currency;
use App\Services\Manufacturing\ProductionPerformanceReportService;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class ProductionPerformanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected string $view = 'filament.pages.manufacturing.production-performance-report';

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing Reports';

    protected static ?string $title = 'Production Performance Analysis';

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()?->can('factory.report.view') ?? false);
    }

    public function table(Table $table): Table
    {
        $currency = config('app.currency', 'NGN');

        if (Schema::hasTable('currencies')) {
            $currency = Currency::query()
                ->where('is_lcy', true)
                ->value('code') ?? $currency;
        }

        return $table
            ->query(app(ProductionPerformanceReportService::class)->query())
            ->columns([
                TextColumn::make('document_number')
                    ->label('Order No')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('item.description')
                    ->label('Finished Good')
                    ->searchable(),

                TextColumn::make('produced_qty_sql')
                    ->label('Produced Qty (Base)')
                    ->numeric(2)
                    ->suffix(fn ($record): string => ' '.($record->base_unit_of_measure ?? 'PCS')),

                TextColumn::make('actual_unit_cost_sql')
                    ->label('Actual Unit Cost')
                    ->money($currency),

                TextColumn::make('standard_cost_source_sql')
                    ->label('Std. Cost Source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cost_rollup' => 'Cost Rollup',
                        'item_standard_cost' => 'Fallback: Item Standard',
                        'order_unit_cost' => 'Fallback: Order Unit Cost',
                        'item_unit_cost' => 'Fallback: Item Unit Cost',
                        default => 'No Standard Cost',
                    })
                    ->color(fn (string $state): string => $state === 'cost_rollup' ? 'success' : 'warning'),

                TextColumn::make('standard_unit_cost_sql')
                    ->label('Standard Unit Cost')
                    ->money($currency),

                TextColumn::make('standard_total_cost_sql')
                    ->label('Standard Total Cost')
                    ->money($currency)
                    ->summarize(Sum::make()->money($currency)),

                TextColumn::make('actual_total_cost_sql')
                    ->label('Total Actual Cost')
                    ->money($currency)
                    ->summarize(Sum::make()->money($currency)),

                TextColumn::make('variance_amount_sql')
                    ->label('Variance ₦')
                    ->money($currency)
                    // ✅ FIXED: Positive variance (over budget) is BAD (danger). Negative is GOOD (success).
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->summarize(Sum::make()->money($currency)),

                TextColumn::make('variance_percent')
                    ->label('Variance %')
                    ->getStateUsing(fn ($record): ?float => $record->variance_percent_sql !== null ? (float) $record->variance_percent_sql : null)
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : number_format($state, 2).'%')
                    ->color(fn (?float $state) => $state === null ? 'gray' : ($state > 5 ? 'danger' : ($state < -5 ? 'success' : 'gray'))),

                TextColumn::make('finished_at')
                    ->label('Completed At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('finished_at', 'desc');
    }
}
