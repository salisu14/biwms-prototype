<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Traits\PreventsEditingPostedRecords;
use App\Models\SalesOrder;
use App\Services\Approval\ApprovalService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    use PreventsEditingPostedRecords;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('submit_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn ($record) => auth()->user()?->can('update', $record) && $record->status === SalesOrderStatus::DRAFT)
                ->action(function ($record) {
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
                ->visible(fn ($record) => auth()->user()?->can('approve', $record) &&
                    $record->status === SalesOrderStatus::PENDING_APPROVAL
                )
                ->requiresConfirmation()
                ->action(function ($record) {
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

            Action::make('post_shipment')
                ->label('Post Shipment')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->visible(fn (SalesOrder $record): bool => (auth()->user()?->can('post', $record) || auth()->user()?->can('update', $record)) &&
                    in_array($record->status, [SalesOrderStatus::APPROVED, SalesOrderStatus::RELEASED], true))
                ->requiresConfirmation()
                ->action(function (SalesOrder $record): void {
                    try {
                        $record->postShipment();
                        Notification::make()->title('Shipment Posted')->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title(collect($exception->errors())->flatten()->first() ?? 'Unable to post shipment')->danger()->send();
                    }
                }),

            Action::make('create_sales_invoice')
                ->label('Create Sales Invoice')
                ->icon('heroicon-o-document-check')
                ->color('primary')
                ->visible(fn (SalesOrder $record): bool => (auth()->user()?->can('post', $record) || auth()->user()?->can('update', $record)) &&
                    in_array($record->status, [SalesOrderStatus::SHIPPED, SalesOrderStatus::PARTIALLY_INVOICED], true))
                ->requiresConfirmation()
                ->action(function (SalesOrder $record): void {
                    try {
                        $record->postInvoice();
                        Notification::make()->title('Sales Invoice Created')->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title(collect($exception->errors())->flatten()->first() ?? 'Unable to create sales invoice')->danger()->send();
                    }
                }),

            Action::make('post_and_invoice')
                ->label('Post + Invoice')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn (SalesOrder $record): bool => (auth()->user()?->can('post', $record) || auth()->user()?->can('update', $record)) &&
                    in_array($record->status, [SalesOrderStatus::APPROVED, SalesOrderStatus::RELEASED, SalesOrderStatus::SHIPPED, SalesOrderStatus::PARTIALLY_INVOICED], true))
                ->requiresConfirmation()
                ->action(function (SalesOrder $record) {
                    try {
                        if (in_array($record->status, [SalesOrderStatus::APPROVED, SalesOrderStatus::RELEASED], true)) {
                            $record->postShipment();
                            $record->refresh();
                        }

                        $postedInvoice = $record->postInvoice();
                        Notification::make()->title('Shipment and Invoice Posted')->success()->send();

                        return redirect(SalesInvoiceResource::getUrl('posted', [
                            'tableSearch' => $postedInvoice->document_number,
                        ]));
                    } catch (ValidationException $exception) {
                        Notification::make()->title(collect($exception->errors())->flatten()->first() ?? 'Unable to post and invoice')->danger()->send();
                    }
                }),

            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (SalesOrder $record): bool => auth()->user()?->can('update', $record) &&
                    ! in_array($record->status, [SalesOrderStatus::CLOSED, SalesOrderStatus::CANCELLED], true))
                ->action(function (SalesOrder $record) {
                    if (! $record->canArchive()) {
                        Notification::make()
                            ->title('Order must be fully shipped and fully invoiced before archiving.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->update(['status' => SalesOrderStatus::CLOSED]);
                    Notification::make()->title('Sales Order Archived')->success()->send();

                    return redirect(SalesOrderResource::getUrl('archived', ['tableSearch' => $record->order_number]));
                }),

            DeleteAction::make(),
        ];
    }
}
