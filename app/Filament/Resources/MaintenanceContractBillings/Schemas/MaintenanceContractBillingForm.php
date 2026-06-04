<?php

namespace App\Filament\Resources\MaintenanceContractBillings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MaintenanceContractBillingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('maintenance_contract_id')
                    ->relationship('maintenanceContract', 'id')
                    ->required(),
                DatePicker::make('billing_date')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('scheduled'),
                Select::make('purchase_invoice_id')
                    ->relationship('purchaseInvoice', 'id'),
                DatePicker::make('actual_invoice_date'),
            ]);
    }
}
