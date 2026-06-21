<?php

namespace App\Filament\Resources\MaintenanceContractBillings\Schemas;

use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use App\Models\MaintenanceContractBilling;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceContractBillingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scope')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('maintenance_contract')
                            ->label('Maintenance Contract')
                            ->state(function (MaintenanceContractBilling $record): string {
                                return $record->maintenanceContract
                                    ? "{$record->maintenanceContract->contract_no} - {$record->maintenanceContract->description}"
                                    : '—';
                            })
                            ->url(fn (MaintenanceContractBilling $record): ?string => $record->maintenanceContract
                                ? MaintenanceContractResource::getUrl('view', ['record' => $record->maintenanceContract])
                                : null),
                        TextEntry::make('status')
                            ->badge(),
                    ]),

                Section::make('Billing')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('billing_date')->date()->label('Billing Date'),
                        TextEntry::make('amount')
                            ->money(fn (MaintenanceContractBilling $record) => $record->maintenanceContract?->currency_code ?? config('app.default_currency', 'NGN'))
                            ->label('Amount'),
                        TextEntry::make('purchase_invoice')
                            ->label('Purchase Invoice')
                            ->state(fn (MaintenanceContractBilling $record): string => $record->purchaseInvoice?->document_number ?? '—'),
                    ]),

                Section::make('Invoicing')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('actual_invoice_date')->date()->label('Actual Invoice Date'),
                        TextEntry::make('purchaseInvoice.document_number')
                            ->label('Invoice No.')
                            ->placeholder('—'),
                    ]),

                Section::make('Metadata')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}
