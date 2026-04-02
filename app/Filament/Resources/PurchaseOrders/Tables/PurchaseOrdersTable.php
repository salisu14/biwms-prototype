<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Data\Purchase\ApprovePurchaseOrderData;
use App\Data\Purchase\CancelPurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Services\Purchase\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order_date', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // FIXED: Handle both string and enum instances
                TextColumn::make('order_type')
                    ->badge()
                    ->label('Type')
                    ->formatStateUsing(function ($state): string {
                        // FIXED: Check if already enum instance
                        $enum = $state instanceof PurchaseOrderType
                            ? $state
                            : PurchaseOrderType::tryFrom($state);
                        return $enum?->label() ?? (string) $state;
                    })
                    ->icon(function ($state): ?string {
                        $enum = $state instanceof PurchaseOrderType
                            ? $state
                            : PurchaseOrderType::tryFrom($state);
                        return $enum?->icon();
                    })
                    ->color(function ($state): string {
                        $enum = $state instanceof PurchaseOrderType
                            ? $state
                            : PurchaseOrderType::tryFrom($state);
                        return $enum?->color() ?? 'gray';
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                // FIXED: Same pattern for status
                TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->formatStateUsing(function ($state): string {
                        $enum = $state instanceof PurchaseOrderStatus
                            ? $state
                            : PurchaseOrderStatus::tryFrom($state);
                        return $enum?->label() ?? (string) $state;
                    })
                    ->icon(function ($state): ?string {
                        $enum = $state instanceof PurchaseOrderStatus
                            ? $state
                            : PurchaseOrderStatus::tryFrom($state);
                        return $enum?->icon();
                    })
                    ->color(function ($state): string {
                        $enum = $state instanceof PurchaseOrderStatus
                            ? $state
                            : PurchaseOrderStatus::tryFrom($state);
                        return $enum?->color() ?? 'gray';
                    }),

                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): string => $record->payment_terms ?? '')
                    ->limit(25),

                TextColumn::make('order_date')
                    ->label('Order Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold'),
            ])->recordActions([
                ViewAction::make(),
                EditAction::make(),

                ActionGroup::make([
                    // APPROVE ACTION
                    Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->status === PurchaseOrderStatus::PENDING)
                        ->action(function ($record, PurchaseOrderService $service) {
                            $service->approve(new ApprovePurchaseOrderData(
                                purchaseOrderId: $record->id,
                                approvedBy: auth()->id()
                            ));

                            Notification::make()->title('Order Approved')->success()->send();
                        }),

                    // CANCEL ACTION
                    Action::make('cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->can_edit && $record->status !== PurchaseOrderStatus::CANCELLED)
                        ->action(function ($record, PurchaseOrderService $service) {
                            try {
                                $service->cancel(new CancelPurchaseOrderData(
                                    purchaseOrderId: $record->id
                                ));
                                Notification::make()->title('Order Cancelled')->success()->send();
                            } catch (\Exception $e) {
                                Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    // RECALCULATE TOTALS ACTION
                    Action::make('recalculate')
                        ->label('Refresh Totals')
                        ->icon('heroicon-o-calculator')
                        ->action(function ($record, PurchaseOrderService $service) {
                            $record->recalculateTotals();
                            Notification::make()->title('Totals Recalculated')->success()->send();
                        }),
                ])
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PurchaseOrderStatus::options()),

                SelectFilter::make('order_type')
                    ->label('Type')
                    ->options(PurchaseOrderType::options()),

                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable()
                    ->preload(),
            ])
//            ->recordActions([
//                ViewAction::make(),
//                EditAction::make(),
//            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
