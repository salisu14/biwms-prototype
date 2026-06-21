<?php

namespace App\Filament\Resources\ItemLedgerEntries\Schemas;

use App\Models\Item;
use App\Models\Location;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemLedgerEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make('General Information')
                            ->schema([
                                TextInput::make('entry_number')
                                    ->required()
                                    ->numeric()
                                    ->disabledOn('edit'),
                                Select::make('entry_type')
                                    ->options([
                                        'PURCHASE' => 'Purchase',
                                        'SALE' => 'Sale',
                                        'POSITIVE_ADJUSTMENT' => 'Positive Adjustment',
                                        'NEGATIVE_ADJUSTMENT' => 'Negative Adjustment',
                                        'TRANSFER' => 'Transfer',
                                        'CONSUMPTION' => 'Consumption',
                                        'OUTPUT' => 'Output',
                                    ])
                                    ->required()
                                    ->native(false),
                                Grid::make(2)->schema([
                                    TextInput::make('document_type'),
                                    TextInput::make('document_number')
                                        ->required(),
                                ]),
                                TextInput::make('document_line_number')
                                    ->required()
                                    ->numeric(),
                                Toggle::make('open')
                                    ->label('Entry is Open')
                                    ->helperText('Unapplied quantity remains')
                                    ->required(),
                            ])->columnSpan(2),

                        Section::make('Dates')
                            ->schema([
                                DatePicker::make('posting_date')
                                    ->required()
                                    ->native(false),
                                DateTimePicker::make('entry_date')
                                    ->default(now())
                                    ->required()
                                    ->native(false),
                                DatePicker::make('expiration_date')
                                    ->native(false),
                            ])->columnSpan(1),
                    ]),

                Section::make('Item & Location Details')
                    ->columns(3)
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->relationship('item', 'item_code')
                            ->getOptionLabelFromRecordUsing(fn (Item $record): string => "{$record->item_code} - {$record->description}")
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('variant_code'),
                        Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Location $record): string => "{$record->code} - {$record->name}")
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('bin_code'),
                        TextInput::make('serial_number'),
                        TextInput::make('lot_number'),
                    ]),

                Section::make('Quantities & Financials')
                    ->columns(3)
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->required()
                            ->numeric()
                            ->prefixIcon('heroicon-m-hashtag'),
                        TextInput::make('remaining_quantity')
                            ->required()
                            ->numeric()
                            ->prefixIcon('heroicon-m-calculator'),
                        TextInput::make('cost_amount_actual')
                            ->label('Cost (Actual)')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('cost_amount_expected')
                            ->label('Cost (Expected)')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('purchase_amount_actual')
                            ->label('Purchase Amount')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                    ]),

                Section::make('Posting & Source')
                    ->description('Accounting and source reference metadata')
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        Select::make('inventory_posting_group_id')
                            ->relationship('inventoryPostingGroup', 'code')
                            ->required(),
                        Select::make('general_product_posting_group_id')
                            ->relationship('generalProductPostingGroup', 'code')
                            ->required(),
                        Select::make('general_business_posting_group_id')
                            ->relationship('generalBusinessPostingGroup', 'code'),
                        TextInput::make('source_type'),
                        TextInput::make('source_id')
                            ->numeric(),
                        Select::make('applied_entry_id')
                            ->relationship('appliedEntry', 'entry_number')
                            ->searchable(),
                    ]),
            ]);
    }
}
