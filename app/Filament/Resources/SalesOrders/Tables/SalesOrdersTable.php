<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Services\Approval\ApprovalService;
use App\Services\Print\ProformaInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->money(fn ($record) => $record instanceof SalesOrder ? $record->currency_code : 'NGN')
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(function (SalesOrder $record): string {
                        $uoms = $record->lines()->pluck('unit_of_measure_code')->filter()->unique();

                        if ($uoms->count() === 1) {
                            return ' '.$uoms->first();
                        }

                        return ' Units';
                    })
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('total_quantity_shipped')
                    ->label('Shipped Qty')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(function (SalesOrder $record): string {
                        $uoms = $record->lines()->pluck('unit_of_measure_code')->filter()->unique();

                        if ($uoms->count() === 1) {
                            return ' '.$uoms->first();
                        }

                        return ' Units';
                    })
                    ->sortable()
                    ->alignment('right')
                    ->color('success'),

                TextColumn::make('total_quantity_to_ship')
                    ->label('To Ship Qty')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(function (SalesOrder $record): string {
                        $uoms = $record->lines()->pluck('unit_of_measure_code')->filter()->unique();

                        if ($uoms->count() === 1) {
                            return ' '.$uoms->first();
                        }

                        return ' Units';
                    })
                    ->sortable()
                    ->alignment('right')
                    ->color('warning'),

                IconColumn::make('fully_shipped')
                    ->label('Shipped')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('fully_invoiced')
                    ->label('Invoiced')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('salesperson.name')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SalesOrderStatus::class),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && ! $record->isPosted()),

                EditAction::make()
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && ! $record->isPosted())
                    ->disabled(fn ($record): bool => $record instanceof SalesOrder &&
                        ! auth()->user()?->hasRole('SUPER_ADMIN') &&
                        ($record->isPosted() || $record->status === SalesOrderStatus::APPROVED)
                    ),

                // Custom "Reverse" action for posted invoices
                Action::make('reverse')
                    ->label('Reverse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && $record->isPosted())
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record): void {
                        try {
                            $record->reverse();
                            Notification::make()
                                ->title('Shipment Reversed')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(collect($exception->errors())->flatten()->first() ?? 'Unable to reverse shipment')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('post_shipment')
                    ->label('Post Shipment')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && in_array($record->status, [SalesOrderStatus::APPROVED, SalesOrderStatus::RELEASED], true))
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record): void {
                        try {
                            $record->postShipment();
                            Notification::make()
                                ->title('Shipment Posted')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(collect($exception->errors())->flatten()->first() ?? 'Unable to post shipment')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('post_invoice')
                    ->label('Post Invoice')
                    ->icon('heroicon-o-document-check')
                    ->color('primary')
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && in_array($record->status, [SalesOrderStatus::SHIPPED, SalesOrderStatus::PARTIALLY_INVOICED], true))
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record): void {
                        try {
                            $record->postInvoice();
                            Notification::make()
                                ->title('Invoice Posted')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(collect($exception->errors())->flatten()->first() ?? 'Unable to post invoice')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('printProforma')
                    ->label('Proforma Invoice')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->visible(fn ($record): bool => $record instanceof SalesOrder)
                    ->action(fn (SalesOrder $record) => response()->streamDownload(
                        fn () => print (app(ProformaInvoiceService::class)->generateSalesProforma($record->refresh()->load(['lines']))->output()),
                        $record->order_number.'_Proforma.pdf'
                    )),

                Action::make('submit_approval')
                    ->label('Submit for Approval')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn ($record) => $record instanceof SalesOrder && $record->status === SalesOrderStatus::DRAFT)
                    ->action(function (SalesOrder $record) {
                        app(ApprovalService::class)->submitForApproval($record);
                        Notification::make()
                            ->title('Submitted for Approval')
                            ->success()
                            ->send();
                    }),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record instanceof SalesOrder &&
                        $record->status === SalesOrderStatus::PENDING_APPROVAL &&
                        $record->approvalEntries()->where('status', 'created')
                            ->where(function ($q) {
                                $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id());
                            })
                            ->exists()
                    )
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        $entry = $record->approvalEntries()->where('status', 'created')
                            ->where(function ($q) {
                                $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id());
                            })
                            ->orderBy('sequence_no')
                            ->first();

                        if (! $entry) {
                            Notification::make()->title('No pending approval')->danger()->send();

                            return;
                        }

                        app(ApprovalService::class)->approve($entry);
                        Notification::make()
                            ->title('Order Approved')
                            ->success()
                            ->send();
                    }),

                // Super Admin Status Override
                Action::make('changeStatus')
                    ->label('Change Status')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && auth()->user()?->hasRole('super_admin'))
                    ->form([
                        Select::make('status')
                            ->options(SalesOrderStatus::class)
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (SalesOrder $record, array $data) {
                        $record->update(['status' => $data['status']]);
                        Notification::make()
                            ->title('Status Updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
