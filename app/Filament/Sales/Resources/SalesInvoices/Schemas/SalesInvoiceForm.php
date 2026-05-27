<?php

namespace App\Filament\Sales\Resources\SalesInvoices\Schemas;

use App\Services\NumberSeriesService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->schema([
                        TextInput::make('document_no')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => NumberSeriesService::getNextNo('S-INV'))
                            ->disabled()
                            ->dehydrated(),

                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('sales_order_id')
                            ->relationship('salesOrder', 'document_no')
                            ->searchable()
                            ->preload()
                            ->placeholder('No related order'),

                        DatePicker::make('document_date')
                            ->default(now())
                            ->required(),

                        DatePicker::make('due_date')
                            ->default(now()->addDays(30))
                            ->required(),

                        Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'paid' => 'Paid',
                                'partially_paid' => 'Partially Paid',
                                'overdue' => 'Overdue',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('open')
                            ->required()
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Invoice Lines')
                    ->schema([
                        Repeater::make('lines')
                            ->relationship('lines')
                            ->schema([
                                Select::make('item_id')
                                    ->relationship('item', 'description')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required(),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),

                                TextInput::make('line_amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->columns(4)
                            ->addActionLabel('Add Line')
                            ->reorderable()
                            ->collapsible(),
                    ]),

                Section::make('Totals')
                    ->schema([
                        TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('vat_amount')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('amount_paid')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('balance_due')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),
            ]);
    }
}
