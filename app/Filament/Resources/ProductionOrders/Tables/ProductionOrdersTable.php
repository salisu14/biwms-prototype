<?php

namespace App\Filament\Resources\ProductionOrders\Tables;

use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                    ->color(fn(ProductionOrderStatus $state): string => match ($state) {
                        ProductionOrderStatus::SIMULATED => 'gray',
                        ProductionOrderStatus::PLANNED => 'info',
                        ProductionOrderStatus::FIRM_PLANNED => 'warning',
                        ProductionOrderStatus::RELEASED => 'success',
                        ProductionOrderStatus::FINISHED => 'success',
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
                Action::make('refresh')
                    ->label('Refresh Lines')
                    ->icon('heroicon-m-arrow-path')
                    ->visible(fn($record) => $record->status->isEditable())
                    ->action(fn($record) => $record->refreshOrder()),

                Action::make('release')
                    ->label('Release')
                    ->icon('heroicon-m-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === ProductionOrderStatus::FIRM_PLANNED)
                    ->action(function ($record) {
                        try {
                            $record->changeStatus(ProductionOrderStatus::RELEASED->value, auth()->id());
                            Notification::make()
                                ->title('Production Order Released')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('finish')
                    ->label('Finish')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === ProductionOrderStatus::RELEASED)
                    ->action(function ($record) {
                        try {
                            $record->finish(auth()->id());
                            Notification::make()
                                ->title('Production Order Finished')
                                ->body("Unit cost: $" . number_format($record->fresh()->unit_cost, 2))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->visible(fn($record) => $record->status->isEditable()),

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
