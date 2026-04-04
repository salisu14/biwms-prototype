<?php

namespace App\Filament\Pages\Manufacturing;

use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\ProductionOrder;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ProductionPerformanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected string $view = 'filament.pages.manufacturing.production-performance-report';

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing Reports';

    protected static ?string $title = 'Production Performance Analysis';

    public function table(Table $table): Table
    {
        return $table
            ->query(ProductionOrder::query()->where('status', ProductionOrderStatus::FINISHED))
            ->columns([
                TextColumn::make('document_number')
                    ->label('Order No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item.description')
                    ->label('Finished Good')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Produced Qty')
                    ->numeric(2),
                TextColumn::make('unit_cost')
                    ->label('Actual Unit Cost')
                    ->money('USD'),
                TextColumn::make('cost_rollup')
                    ->label('Standard Unit Cost')
                    ->money('USD'),
                TextColumn::make('total_actual_cost')
                    ->label('Total Actual Cost')
                    ->money('USD')
                    ->summarize(Sum::make()->money('USD')),
                TextColumn::make('cost_variance')
                    ->label('Variance $')
                    ->money('USD')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->summarize(Sum::make()->money('USD')),
                TextColumn::make('variance_percent')
                    ->label('Variance %')
                    ->getStateUsing(function ($record) {
                        $standard = $record->cost_rollup * $record->quantity;
                        if ($standard == 0) {
                            return 0;
                        }

                        return ($record->cost_variance / $standard) * 100;
                    })
                    ->numeric(2)
                    ->suffix('%')
                    ->color(fn ($state) => $state > 5 ? 'danger' : ($state < -5 ? 'success' : 'gray')),
                TextColumn::make('finished_at')
                    ->label('Completed At')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('finished_at', 'desc');
    }
}
