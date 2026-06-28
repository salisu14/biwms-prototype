<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Pages\Finance\CustomerSubledgerSummary;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedSalesInvoice;
use App\Services\Finance\PaymentService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Builder;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('post')
                ->label('Post')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => (auth()->user()?->can('post', $record) ?? false) && $record->status === 'PENDING')
                ->action(function ($record, PaymentService $service) {
                    $service->post($record, auth()->id());
                    Notification::make()
                        ->title('Payment Posted')
                        ->success()
                        ->send();
                }),

            Action::make('apply')
                ->label('Apply to Documents')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->visible(fn ($record) => (auth()->user()?->can('apply', $record) ?? false) && $record->status === 'POSTED' && $record->unapplied_amount > 0)
                ->schema([
                    Select::make('document_type')
                        ->options([
                            'SALES_INVOICE' => 'Sales Invoice',
                            'PURCHASE_INVOICE' => 'Purchase Invoice',
                        ])
                        ->default(fn ($record) => $record->party_type === 'CUSTOMER' ? 'SALES_INVOICE' : 'PURCHASE_INVOICE')
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($set) => $set('document_id', null)),
                    Select::make('document_id')
                        ->label('Document')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->options(fn ($get, $record) => $get('document_type') === 'SALES_INVOICE'
                                ? PostedSalesInvoice::forCustomer($record->party_id)
                                    ->where(fn (Builder $query) => $query
                                        ->where('remaining_amount', '>', 0)
                                        ->orWhereNull('remaining_amount'))
                                    ->where(fn (Builder $query) => $query
                                        ->where('cancelled', false)
                                        ->orWhereNull('cancelled'))
                                    ->pluck('document_number', 'id')
                                : PostedPurchaseInvoice::forVendor($record->party_id)
                                    ->where(fn (Builder $query) => $query
                                        ->where('remaining_amount', '>', 0)
                                        ->orWhereNull('remaining_amount'))
                                    ->where(fn (Builder $query) => $query
                                        ->where('cancelled', false)
                                        ->orWhereNull('cancelled'))
                                    ->pluck('document_number', 'id')
                        )
                        ->helperText(function ($get, $record): string {
                            $count = $get('document_type') === 'SALES_INVOICE'
                                ? PostedSalesInvoice::forCustomer($record->party_id)
                                    ->where(fn (Builder $query) => $query->where('remaining_amount', '>', 0)->orWhereNull('remaining_amount'))
                                    ->where(fn (Builder $query) => $query->where('cancelled', false)->orWhereNull('cancelled'))
                                    ->count()
                                : PostedPurchaseInvoice::forVendor($record->party_id)
                                    ->where(fn (Builder $query) => $query->where('remaining_amount', '>', 0)->orWhereNull('remaining_amount'))
                                    ->where(fn (Builder $query) => $query->where('cancelled', false)->orWhereNull('cancelled'))
                                    ->count();

                            return $count > 0
                                ? "{$count} open document(s) available."
                                : 'No posted open documents found for this party. Post an invoice first.';
                        })
                        ->required(),
                    TextInput::make('amount')
                        ->numeric()
                        ->prefix(fn ($record) => $record->currency?->symbol ?? '$')
                        ->helperText(fn ($record) => 'Max: '.$record->unapplied_amount)
                        ->required(),
                ])
                ->action(function (array $data, $record, PaymentService $service) {
                    $service->applyToDocument($record, $data, auth()->id());
                    Notification::make()
                        ->title('Application Successful')
                        ->success()
                        ->send();
                }),

            Action::make('openInvoices')
                ->label('Open Invoice List')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn ($record) => $record->party_type === 'CUSTOMER'
                    ? route('filament.admin.resources.sales-invoices.index')
                    : route('filament.admin.resources.purchase-invoices.index'))
                ->openUrlInNewTab(),

            Action::make('openCustomerSubledger')
                ->label('Open Customer Subledger')
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->visible(fn ($record) => $record->party_type === 'CUSTOMER' && filled($record->party_id))
                ->url(fn ($record) => CustomerSubledgerSummary::getUrl([
                    'customerId' => $record->party_id,
                ])),

            Action::make('markReconciled')
                ->label('Mark Reconciled')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => auth()->user()?->can('reconcile', $record) ?? false)
                ->disabled(fn ($record) => $record->status !== 'POSTED'
                    || (bool) $record->reconciled
                    || empty($record->bank_account_id))
                ->tooltip(fn ($record) => $record->status !== 'POSTED'
                    ? 'Only posted payments can be reconciled.'
                    : ((bool) $record->reconciled
                        ? 'Payment is already reconciled.'
                        : (empty($record->bank_account_id)
                            ? 'Set a bank account before reconciliation.'
                            : null)))
                ->action(function ($record) {
                    $record->update([
                        'reconciled' => true,
                        'status' => 'RECONCILED',
                        'reconciled_at' => now(),
                        'reconciled_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Payment Reconciled')
                        ->success()
                        ->send();
                }),

            Action::make('undoReconciled')
                ->label('Undo Reconciliation')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn ($record) => auth()->user()?->can('reconcile', $record) ?? false)
                ->disabled(fn ($record) => $record->status !== 'POSTED' || ! (bool) $record->reconciled)
                ->tooltip(fn ($record) => $record->status !== 'POSTED'
                    ? 'Only posted payments can be unreconciled.'
                    : (! (bool) $record->reconciled ? 'Payment is not reconciled yet.' : null))
                ->action(function ($record) {
                    $record->update([
                        'reconciled' => false,
                        'status' => 'POSTED',
                        'reconciled_at' => null,
                        'reconciled_by' => null,
                    ]);

                    Notification::make()
                        ->title('Reconciliation Reversed')
                        ->success()
                        ->send();
                }),

            Action::make('void')
                ->label('Void')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->form(SensitiveActionPasswordConfirmation::schemaWithPasswordConfirmation([
                    Textarea::make('reason')
                        ->required(),
                ]))
                ->visible(fn ($record) => (auth()->user()?->can('void', $record) ?? false) && $record->status === 'POSTED' && ! $record->reconciled)
                ->action(function (array $data, $record, PaymentService $service) {
                    $service->void($record, $data['reason'], auth()->id());
                    Notification::make()
                        ->title('Payment Voided')
                        ->success()
                        ->send();
                }),
        ];
    }
}
