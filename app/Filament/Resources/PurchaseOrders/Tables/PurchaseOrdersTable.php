<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Data\Purchase\ApprovePurchaseOrderData;
use App\Data\Purchase\CancelPurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\Print\PostedPurchaseInvoicePrintService;
use App\Services\Print\ProformaInvoiceService;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchase\PurchaseOrderService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    ->money(fn (PurchaseOrder $record) => $record->currency_code ?: 'USD')
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
                        ->visible(fn ($record) => $record instanceof PurchaseOrder && $record->status === PurchaseOrderStatus::PENDING)
                        ->action(function ($record, PurchaseOrderService $service) {
                            if (! $record instanceof PurchaseOrder) {
                                return;
                            }
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
                        ->visible(fn ($record) => $record instanceof PurchaseOrder && in_array($record->status, [PurchaseOrderStatus::PENDING, PurchaseOrderStatus::APPROVED], true))
                        ->action(function ($record, PurchaseOrderService $service) {
                            if (! $record instanceof PurchaseOrder) {
                                return;
                            }
                            try {
                                $service->cancel(new CancelPurchaseOrderData(
                                    purchaseOrderId: $record->id
                                ));
                                Notification::make()->title('Order Cancelled')->success()->send();
                            } catch (Exception $e) {
                                Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Action::make('markPartiallyReceived')
                        ->label('Mark Partially Received')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('warning')
                        ->schema(function (PurchaseOrder $record): array {
                            $record->loadMissing('lines');

                            return [
                                Repeater::make('lines')
                                    ->label('Receive Quantities (per line)')
                                    ->default(
                                        $record->lines->map(fn ($line): array => [
                                            'line_id' => $line->id,
                                            'line_number' => $line->line_number,
                                            'item_code' => $line->item_code,
                                            'description' => $line->description,
                                            'ordered_qty' => (float) $line->quantity,
                                            'already_received' => (float) $line->received_quantity,
                                            'remaining_qty' => max(0, (float) $line->quantity - (float) $line->received_quantity),
                                            'receive_qty' => 0,
                                        ])->values()->all()
                                    )
                                    ->schema([
                                        TextInput::make('line_id')->hidden()->dehydrated(),
                                        TextInput::make('line_number')->hidden()->dehydrated(),
                                        TextInput::make('item_code')->label('Item')->disabled()->dehydrated(false),
                                        TextInput::make('description')->disabled()->dehydrated(false),
                                        TextInput::make('ordered_qty')->label('Ordered')->numeric()->disabled()->dehydrated(false),
                                        TextInput::make('already_received')->label('Received')->numeric()->disabled()->dehydrated(false),
                                        TextInput::make('remaining_qty')->label('Remaining')->numeric()->disabled()->dehydrated(false),
                                        TextInput::make('receive_qty')
                                            ->label('Receive Now')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),
                                    ])
                                    ->columns(7)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false),
                            ];
                        })
                        ->visible(fn ($record) => $record instanceof PurchaseOrder && in_array($record->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::PENDING, PurchaseOrderStatus::PARTIALLY_RECEIVED], true))
                        ->action(function ($record, array $data) {
                            if (! $record instanceof PurchaseOrder) {
                                return;
                            }
                            try {
                                app(PurchaseOrderService::class)->receivePartial($record->id, $data['lines'] ?? []);
                                Notification::make()->title('Receipt quantities updated successfully')->success()->send();
                            } catch (Exception $exception) {
                                $message = $exception->getMessage();
                                $isOverReceipt = str_contains($message, 'cannot exceed remaining quantity');

                                Notification::make()
                                    ->title($isOverReceipt ? 'Invalid receive quantity' : 'Nothing to receive')
                                    ->body($message)
                                    ->{$isOverReceipt ? 'danger' : 'warning'}()
                                    ->send();
                            }
                        }),

                    Action::make('post_receipt')
                        ->label('Post Receipt')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record instanceof PurchaseOrder && in_array($record->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::PARTIALLY_RECEIVED], true))
                        ->action(function ($record, PurchaseOrderService $purchaseOrderService) {
                            if (! $record instanceof PurchaseOrder) {
                                return;
                            }

                            $purchaseOrderService->postReceipt($record);
                            Notification::make()->title('Receipt posted')->success()->send();
                        }),

                    Action::make('create_purchase_invoice')
                        ->label('Create Purchase Invoice')
                        ->icon('heroicon-o-document-check')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record instanceof PurchaseOrder && in_array($record->status, [PurchaseOrderStatus::RECEIVED, PurchaseOrderStatus::PARTIALLY_RECEIVED], true))
                        ->action(function ($record, PurchaseInvoiceService $purchaseInvoiceService) {
                            if (! $record instanceof PurchaseOrder) {
                                return;
                            }

                            try {
                                $invoice = $purchaseInvoiceService->createFromOrder($record);
                                Notification::make()->title('Purchase Invoice Created')->success()->send();

                                return redirect(PurchaseInvoiceResource::getUrl('edit', ['record' => $invoice]));
                            } catch (\RuntimeException $exception) {
                                Notification::make()->title($exception->getMessage())->warning()->send();

                                return null;
                            }
                        }),

                    Action::make('post_and_invoice')
                        ->label('Post + Invoice')
                        ->icon('heroicon-o-bolt')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record instanceof PurchaseOrder && in_array($record->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::RECEIVED], true))
                        ->action(function ($record, PurchaseInvoiceService $purchaseInvoiceService) {
                            if (! $record instanceof PurchaseOrder) {
                                return;
                            }

                            app(PurchaseOrderService::class)->postReceipt($record);
                            $record->refresh();

                            try {
                                $invoice = $purchaseInvoiceService->createFromOrder($record);
                                $postedInvoice = $purchaseInvoiceService->post($invoice);
                                Notification::make()->title('Receipt and Invoice Posted')->success()->send();

                                return redirect(PurchaseOrderResource::getUrl('archived', [
                                    'tableSearch' => $record->order_number,
                                ]));
                            } catch (\RuntimeException $exception) {
                                Notification::make()->title($exception->getMessage())->warning()->send();

                                return null;
                            }
                        }),

                    Action::make('close')
                        ->label('Close Order')
                        ->icon('heroicon-o-lock-closed')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record instanceof PurchaseOrder && in_array($record->status, [PurchaseOrderStatus::RECEIVED, PurchaseOrderStatus::INVOICED, PurchaseOrderStatus::PARTIALLY_RECEIVED], true))
                        ->action(function ($record) {
                            if (! $record instanceof PurchaseOrder) {
                                return;
                            }

                            $record->update(['status' => PurchaseOrderStatus::CLOSED]);
                            Notification::make()->title('Order closed')->success()->send();
                        }),

                    Action::make('reopen')
                        ->label('Reopen to Pending')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record instanceof PurchaseOrder && in_array($record->status, [PurchaseOrderStatus::CANCELLED, PurchaseOrderStatus::CLOSED], true))
                        ->action(function ($record, PurchaseOrderService $service) {
                            if (! $record instanceof PurchaseOrder) {
                                return;
                            }

                            try {
                                $service->reopen($record->id);
                                Notification::make()->title('Order reopened')->success()->send();
                            } catch (Exception $exception) {
                                Notification::make()->title('Unable to reopen order')->body($exception->getMessage())->danger()->send();
                            }
                        }),

                    // RECALCULATE TOTALS ACTION
                    Action::make('recalculate')
                        ->label('Refresh Totals')
                        ->icon('heroicon-o-calculator')
                        ->visible(fn ($record) => $record instanceof PurchaseOrder)
                        ->action(function ($record, PurchaseOrderService $service) {
                            if (! $record instanceof PurchaseOrder) {
                                return;
                            }
                            $record->recalculateTotals();
                            Notification::make()->title('Totals Recalculated')->success()->send();
                        }),

                    Action::make('printProforma')
                        ->label('Purchase Order (PO)')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->visible(fn ($record) => $record instanceof PurchaseOrder)
                        ->action(fn ($record) => $record instanceof PurchaseOrder ? response()->streamDownload(
                            fn () => print (app(ProformaInvoiceService::class)->generatePurchaseProforma($record->refresh()->load(['lines']))->output()),
                            $record->order_number.'_PO.pdf'
                        ) : null),

                    Action::make('printPurchaseInvoice')
                        ->label('Purchase Invoice (PI)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->visible(fn ($record) => $record instanceof PurchaseOrder)
                        ->action(function ($record) {
                            if (! $record instanceof PurchaseOrder) {
                                return null;
                            }

                            $postedInvoice = PostedPurchaseInvoice::query()
                                ->where('order_id', $record->id)
                                ->latest('id')
                                ->first();

                            if (! $postedInvoice) {
                                Notification::make()
                                    ->title('No posted purchase invoice found for this order.')
                                    ->warning()
                                    ->send();

                                return null;
                            }

                            return response()->streamDownload(
                                fn () => print (app(PostedPurchaseInvoicePrintService::class)->generatePurchaseInvoice($postedInvoice)->output()),
                                $postedInvoice->document_number.'_PI.pdf'
                            );
                        }),
                ]),
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
