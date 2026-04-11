<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\PostedSalesInvoice;
use App\Models\PurchaseInvoice;
use App\Services\Finance\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notifications;
use Filament\Resources\Pages\ViewRecord;

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
                ->visible(fn ($record) => $record->status === 'PENDING')
                ->action(function ($record, PaymentService $service) {
                    $service->post($record, auth()->id());
                    Notifications::make()
                        ->title('Payment Posted')
                        ->success()
                        ->send();
                }),

            Action::make('apply')
                ->label('Apply to Documents')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->visible(fn ($record) => $record->status === 'POSTED' && $record->unapplied_amount > 0)
                ->form([
                    Select::make('document_type')
                        ->options([
                            'SALES_INVOICE' => 'Sales Invoice',
                            'PURCHASE_INVOICE' => 'Purchase Invoice',
                        ])
                        ->required()
                        ->live(),
                    Select::make('document_id')
                        ->label('Document')
                        ->searchable()
                        ->options(fn ($get, $record) => $get('document_type') === 'SALES_INVOICE'
                                ? PostedSalesInvoice::forCustomer($record->party_id)->where('paid_in_full', false)->pluck('document_number', 'id')
                                : PurchaseInvoice::forVendor($record->party_id)->where('paid_in_full', false)->pluck('document_number', 'id')
                        )
                        ->required(),
                    TextInput::make('amount')
                        ->numeric()
                        ->prefix(fn ($record) => $record->currency?->symbol ?? '$')
                        ->helperText(fn ($record) => 'Max: '.$record->unapplied_amount)
                        ->required(),
                ])
                ->action(function (array $data, $record, PaymentService $service) {
                    $service->applyToDocument($record, $data, auth()->id());
                    Notifications::make()
                        ->title('Application Successful')
                        ->success()
                        ->send();
                }),

            Action::make('void')
                ->label('Void')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Textarea::make('reason')
                        ->required(),
                ])
                ->visible(fn ($record) => $record->status === 'POSTED' && ! $record->reconciled)
                ->action(function (array $data, $record, PaymentService $service) {
                    $service->void($record, $data['reason'], auth()->id());
                    Notifications::make()
                        ->title('Payment Voided')
                        ->success()
                        ->send();
                }),
        ];
    }
}
