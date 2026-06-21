<?php

namespace App\Filament\Resources\MaintenanceContractBillings\Schemas;

use App\Models\MaintenanceContract;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MaintenanceContractBillingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Billing Schedule')
                    ->description('Define the scheduled billing date and amount for the maintenance contract.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('maintenance_contract_id')
                                    ->label('Maintenance Contract')
                                    ->relationship('maintenanceContract', 'contract_no')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(
                                        fn (MaintenanceContract $record) => "{$record->contract_no} — {$record->description}"
                                    )
                                    ->getSearchResultsUsing(
                                        fn (string $search) => MaintenanceContract::where('contract_no', 'like', "%{$search}%")
                                            ->orWhere('description', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($c) => [$c->id => "{$c->contract_no} — {$c->description}"])
                                    ),

                                DatePicker::make('billing_date')
                                    ->label('Billing Date')
                                    ->required()
                                    ->native(false)
                                    ->default(now()),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Billing Amount')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₦')
                                    ->minValue(0)
                                    ->step(0.01),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'scheduled' => 'Scheduled',
                                        'invoiced' => 'Invoiced',
                                        'paid' => 'Paid',
                                    ])
                                    ->required()
                                    ->default('scheduled')
                                    ->native(false)
                                    ->live(),
                            ]),
                    ]),

                Section::make('Invoicing Details')
                    ->description('Link the purchase invoice once the bill is received from the vendor.')
                    ->visible(fn (Get $get) => in_array($get('status'), ['invoiced', 'paid']))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('purchase_invoice_id')
                                    ->label('Purchase Invoice')
                                    ->relationship('purchaseInvoice', 'document_number')
                                    ->searchable()
                                    ->preload(),

                                DatePicker::make('actual_invoice_date')
                                    ->label('Actual Invoice Date')
                                    ->native(false),
                            ]),
                    ]),
            ]);
    }
}
