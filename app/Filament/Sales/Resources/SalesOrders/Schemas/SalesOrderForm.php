<?php

namespace App\Filament\Sales\Resources\SalesOrders\Schemas;

use App\Models\Item;
use App\Services\NumberSeriesService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesOrderForm
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
                            ->default(fn () => NumberSeriesService::getNextNo('S-ORD')),

                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        DatePicker::make('document_date')
                            ->default(now())
                            ->required(),

                        DatePicker::make('requested_delivery_date')
                            ->required(),

                        Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'released' => 'Released',
                                'partially_shipped' => 'Partially Shipped',
                                'shipped' => 'Shipped',
                                'invoiced' => 'Invoiced',
                            ])
                            ->default('open')
                            ->required()
                            ->disabled(fn ($operation) => $operation === 'create'),
                    ])
                    ->columns(2),

                Section::make('Lines')
                    ->schema([
                        Repeater::make('lines')
                            ->relationship('lines')
                            ->schema([
                                Select::make('item_id')
                                    ->relationship('item', 'description')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $item = Item::find($state);
                                        if ($item) {
                                            $set('unit_price', $item->unit_price);
                                        }
                                    }),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $set('line_amount', $state * $get('unit_price'));
                                    }),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(),

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
            ]);
    }
}
