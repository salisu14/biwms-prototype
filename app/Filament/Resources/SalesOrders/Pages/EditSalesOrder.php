<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Traits\PreventsEditingPostedRecords;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Approval\ApprovalService;
use App\Support\SalesOrderPostingActionHandler;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    use PreventsEditingPostedRecords;

    protected ?bool $hasDatabaseTransactions = true;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (ValidationException $exception) {
            $this->notifyFailure('Sales order was not saved.', $this->validationMessage($exception));

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Sales order Filament edit failed.', [
                'sales_order_id' => $this->record?->getKey(),
                'order_number' => $this->record?->getAttribute('order_number'),
                'exception' => $exception,
            ]);

            $this->notifyFailure('Sales order was not saved.', 'Sales order could not be saved. Please review the form and try again.');

            throw ValidationException::withMessages([
                'data' => 'Sales order could not be saved. Please review the form and try again.',
            ]);
        }
    }

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $customer = $record->customer?->name ?: $record->customer_name ?: 'Unknown Customer';

        return ($record->order_number ?? 'Sales Order')
            .' • Scope '.$customer
            .' • Attribute '.($record->status?->label() ?? $record->status?->value ?? 'Unknown Status');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();
        $location = $record->location?->code
            ? "{$record->location->code} - {$record->location->name}"
            : ($record->location?->name ?? 'Unknown Location');

        return ($record->external_document_number ?: 'No external reference')
            .' • '.$location
            .' • '.number_format((float) $record->grand_total, 2).' '.($record->currency_code ?: '');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();
        $customer = $record->customer?->name ?: $record->customer_name ?: 'Unknown Customer';

        return ($record->order_number ?? 'Sales Order').' - '.$customer;
    }

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
                    app(SalesOrderPostingActionHandler::class)->postShipment($record);
                }),

            Action::make('create_sales_invoice')
                ->label('Create Sales Invoice')
                ->icon('heroicon-o-document-check')
                ->color('primary')
                ->visible(fn (SalesOrder $record): bool => (auth()->user()?->can('post', $record) || auth()->user()?->can('update', $record)) &&
                    in_array($record->status, [SalesOrderStatus::SHIPPED, SalesOrderStatus::PARTIALLY_INVOICED], true))
                ->requiresConfirmation()
                ->action(function (SalesOrder $record): void {
                    app(SalesOrderPostingActionHandler::class)->postInvoice($record);
                }),

            Action::make('post_and_invoice')
                ->label('Post + Invoice')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn (SalesOrder $record): bool => (auth()->user()?->can('post', $record) || auth()->user()?->can('update', $record)) &&
                    in_array($record->status, [SalesOrderStatus::APPROVED, SalesOrderStatus::RELEASED, SalesOrderStatus::SHIPPED, SalesOrderStatus::PARTIALLY_INVOICED], true))
                ->requiresConfirmation()
                ->action(function (SalesOrder $record) {
                    return app(SalesOrderPostingActionHandler::class)->postAndInvoice($record);
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

    protected function afterSave(): void
    {
        $this->finalizeSalesOrderPersistence();
    }

    protected function finalizeSalesOrderPersistence(): void
    {
        $this->record->refresh()->load('lines');

        $hasValidLine = $this->record->lines
            ->contains(fn (SalesOrderLine $line): bool => (float) $line->quantity > 0);

        if (! $hasValidLine) {
            throw ValidationException::withMessages([
                'lines' => 'Sales order requires at least one item line with a quantity greater than zero.',
            ]);
        }

        $this->record->saveRecalculatedTotalsFromPersistedLines();
    }

    private function notifyFailure(string $title, string $message): void
    {
        Notification::make()
            ->title($title)
            ->body($message)
            ->danger()
            ->send();
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->first()
            ?? 'Please review the highlighted fields and try again.';
    }
}
