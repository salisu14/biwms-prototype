<?php

namespace App\Filament\Resources\ProductionOrders\Tables;

use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\Actions\ProductionOrderActions;
use App\Filament\Resources\ProductionOrders\FinishedProductionOrderResource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\ProductionOrders\ReleasedProductionOrderResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
                    ->searchable()
                    ->limit(32),

                TextColumn::make('quantity')
                    ->state(fn ($record): float => $record->quantityInOrderUom())
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn ($record): string => ' '.$record->orderUomCode()),

                TextColumn::make('due_date')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_actual_cost')
                    ->money('NGN')
                    ->label('Actual Cost')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ProductionOrderStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
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
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
