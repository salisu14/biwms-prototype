<?php

namespace App\Filament\Resources\ProductionOrders\Tables;

use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\Actions\ProductionOrderActions;
use App\Filament\Resources\ProductionOrders\FinishedProductionOrderResource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\ProductionOrders\ReleasedProductionOrderResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductionOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProductionOrderStatus $state): string => match ($state) {
                        ProductionOrderStatus::SIMULATED => 'gray',
                        ProductionOrderStatus::PLANNED => 'info',
                        ProductionOrderStatus::FIRM_PLANNED => 'warning',
                        ProductionOrderStatus::RELEASED => 'success',
                        ProductionOrderStatus::FINISHED => 'primary',
                        ProductionOrderStatus::CANCELLED => 'danger',
                    }),

                TextColumn::make('item.description')
                    ->searchable(),

                TextColumn::make('quantity')
                    ->state(function ($record): float {
                        $quantity = (float) ($record->quantity ?? 0);
                        $quantityBase = (float) ($record->quantity_base ?? 0);
                        $uomCode = (string) ($record->unit_of_measure_code ?? '');

                        if (! $record->item_id || $uomCode === '') {
                            return $quantity;
                        }

                        $item = $record->item;
                        if (! $item) {
                            return $quantity;
                        }

                        $baseUom = (string) ($item->base_unit_of_measure ?? '');
                        if ($baseUom !== '' && strtoupper($uomCode) === strtoupper($baseUom)) {
                            return $quantity;
                        }

                        $assignment = $item->uoms()->where('uom_code', $uomCode)->first();
                        $factor = (float) ($assignment?->pivot?->conversion_factor ?? 1);
                        if ($factor <= 0) {
                            return $quantity;
                        }

                        // If quantity looks stored in base (same as quantity_base), convert for display.
                        if ($quantityBase > 0 && abs($quantity - $quantityBase) < 0.0001) {
                            return $quantity / $factor;
                        }

                        return $quantity;
                    })
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn ($record): string => ' '.($record->unit_of_measure_code ?? 'PCS')),

                TextColumn::make('due_date')
                    ->date(),

                TextColumn::make('total_actual_cost')
                    ->money('USD')
                    ->label('Actual Cost'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ProductionOrderStatus::class),
            ])
            ->recordActions([
                ProductionOrderActions::refresh(),
                ProductionOrderActions::release(),
                ProductionOrderActions::postOutput(),
                ProductionOrderActions::finish(),
                ProductionOrderActions::cancel(),
                ProductionOrderActions::reopen(),

                EditAction::make()
                    ->visible(fn ($record) => $record->status->isEditable()),

                Action::make('view_entries')
                    ->label('View Entries')
                    ->icon('heroicon-m-document-text')
                    ->url(function ($record): string {
                        $resource = match ($record->status) {
                            ProductionOrderStatus::RELEASED => ReleasedProductionOrderResource::class,
                            ProductionOrderStatus::FINISHED => FinishedProductionOrderResource::class,
                            default => ProductionOrderResource::class,
                        };

                        return $resource::getUrl('view', [
                            'record' => $record,
                            'relation' => 'glEntries',
                        ]);
                    })
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
