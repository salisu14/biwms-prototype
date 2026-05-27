<?php

namespace App\Filament\Sales\Resources\SalesQuotes\Schemas;

use App\Models\Item;
use App\Services\NumberSeriesService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesQuoteForm
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
                            ->default(fn () => NumberSeriesService::getNextNo('S-QUOTE'))
                            ->disabled()
                            ->dehydrated(),

                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')->required(),
                                TextInput::make('email')->email()->required(),
                            ]),

                        DatePicker::make('document_date')
                            ->default(now())
                            ->required(),

                        DatePicker::make('valid_until_date')
                            ->default(now()->addDays(30))
                            ->required(),

                        Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'converted' => 'Converted to Order',
                            ])
                            ->default('open')
                            ->required()
                            ->disabled(fn ($operation) => $operation === 'create'),
                    ])
                    ->columns(2),

                Section::make('Quote Lines')
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
                                            $set('unit_of_measure', $item->unit_of_measure);
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
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $set('line_amount', $state * $get('quantity'));
                                    }),

                                TextInput::make('line_discount_percent')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0)
                                    ->maxValue(100)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $amount = $get('quantity') * $get('unit_price');
                                        $discount = $amount * ($state / 100);
                                        $set('line_amount', $amount - $discount);
                                    }),

                                TextInput::make('line_amount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('unit_of_measure')
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add Line')
                            ->reorderable()
                            ->collapsible()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $total = collect($state)->sum('line_amount');
                                $set('total_amount', $total);
                            }),
                    ]),

                Section::make('Totals')
                    ->schema([
                        TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(),
                    ]),
            ]);
    }
}
