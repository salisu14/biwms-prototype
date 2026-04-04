<?php

namespace App\Filament\Resources\ProductionOrders\Tables;

use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\Actions\ProductionOrderActions;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
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
                    ->numeric(decimalPlaces: 2),

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
//                    ->url(fn($record) => ProductionOrderResource::getUrl('entries', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
