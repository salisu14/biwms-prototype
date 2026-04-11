<?php

namespace App\Filament\Resources\ItemLedgers\Schemas;

use App\Models\DocumentHeader;
use App\Models\Item;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemLedgerForm
{
    public static function configure(Schema $schema): Schema
    {
        // Define Entry Types based on Model Logic
        $entryTypes = [
            'Inbound (Stock In)' => [
                'RECEIPT' => 'Goods Receipt',
                'TRANSFER_IN' => 'Transfer In',
                'RETURN' => 'Customer Return',
                'ADJUSTMENT_POS' => 'Positive Adjustment',
                'PRODUCTION_OUTPUT' => 'Production Output',
            ],
            'Outbound (Stock Out)' => [
                'ISSUE' => 'Material Issue',
                'TRANSFER_OUT' => 'Transfer Out',
                'SALE' => 'Sale / Shipment',
                'ADJUSTMENT_NEG' => 'Negative Adjustment',
                'SCRAP' => 'Scrap / Write-off',
            ],
        ];

        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->description('Define what, where, and how much is moving.')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('item_id')
                                ->label('Item')
                                ->relationship('item', 'item_code')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn ($state, callable $set) => self::fillItemDefaults($state, $set)),

                            Select::make('location_id')
                                ->label('Location')
                                ->relationship('location', 'location_name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('entry_type')
                                ->label('Entry Type')
                                ->options($entryTypes)
                                ->required()
                                ->native(false),
                        ]),

                        Grid::make(4)->schema([
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->required()
                                ->numeric()
                                ->step(0.0001) // Precision matching Model
                                ->default(1),

                            Select::make('uom_id')
                                ->label('Unit of Measure')
                                ->relationship('uom', 'uom_code')
                                ->searchable()
                                ->preload()
                                ->required(),

                            TextInput::make('unit_cost')
                                ->label('Unit Cost')
                                ->required()
                                ->numeric()
                                ->prefix('$')
                                ->step(0.0001)
                                ->default(0.00),

                            // Visual helper for total cost (not saved to DB, just calculated)
                            Placeholder::make('total_cost_preview')
                                ->label('Total Value')
                                ->content(function ($get) {
                                    $qty = (float) $get('quantity');
                                    $cost = (float) $get('unit_cost');

                                    return '$'.number_format($qty * $cost, 4);
                                }),
                        ]),

                        Select::make('doc_id')
                            ->label('Reference Document')
                            ->options(DocumentHeader::pluck('doc_no', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        //
                        //                        Select::make('doc_id')
                        //                            ->label('Reference Document')
                        //                            // FIX: Changed 'doc_number' to 'doc_no' to match the Model column
                        //                            ->relationship('document', 'doc_no')
                        //                            ->searchable()
                        //                            ->preload()
                        //                            ->required()
                        //                            ->createOptionForm([
                        //                                // FIX: Changed field name to 'doc_no' to match Model fillable
                        //                                TextInput::make('doc_no')->required()->label('Document Number'),
                        //                                TextInput::make('doc_type')->required()->label('Document Type'),
                        //                                DatePicker::make('doc_date')->required()->label('Document Date'),
                        //                            ]),
                    ]),

                Section::make('Lot & Expiry')
                    ->description('Trackability information for this batch.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('lot_number')
                                ->label('Lot / Batch Number')
                                ->maxLength(50),

                            DatePicker::make('expiry_date')
                                ->label('Expiry Date')
                                ->native(false),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('System Information')
                    ->description('Read-only running balances and audit info.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('balance_after')
                                ->label('Balance After Transaction')
                                ->disabled()
                                ->dehydrated(false) // Don't send to DB, Model calculates it
                                ->numeric()
                                ->formatStateUsing(fn ($record) => $record ? number_format($record->balance_after, 4) : '-'),

                            TextInput::make('cost_after')
                                ->label('Inventory Value After')
                                ->disabled()
                                ->dehydrated(false) // Don't send to DB, Model calculates it
                                ->numeric()
                                ->formatStateUsing(fn ($record) => $record ? '$'.number_format($record->cost_after, 4) : '-'),
                        ]),

                        Hidden::make('created_by')
                            ->default(auth()->id())
                            ->dehydrated(true),
                    ])
                    ->collapsed()
                    ->visible(fn ($context) => $context === 'edit'), // Only show calculated values on edit
            ]);
    }

    /**
     * Helper: Auto-fill Unit Cost based on selected Item (Optional enhancement)
     */
    protected static function fillItemDefaults($itemId, callable $set): void
    {
        if ($itemId) {
            $item = Item::find($itemId);
            if ($item) {
                // Set default cost to item's standard cost if available
                $set('unit_cost', $item->standard_cost ?? 0);
            }
        }
    }
}
