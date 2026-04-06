<?php

namespace App\Filament\Resources\SalesInvoices\RelationManagers;

use App\Models\SalesInvoiceLine;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    /**
     * Updated to use the standard Filament Form object.
     */
//    public function form(Schema $schema): Schema
//    {
//        return $schema
//            ->schema([
//                Grid::make(3)
//                    ->schema([
//                        Select::make('type')
//                            ->options([
//                                'item' => 'Item',
//                                'g/l_account' => 'G/L Account',
//                                'charge' => 'Charge (Item)',
//                                'fixed_asset' => 'Fixed Asset',
//                            ])
//                            ->default('item')
//                            ->required()
//                            ->live(),
//
//                        Select::make('item_id')
//                            ->label('Item')
//                            // Display item_number - description in the dropdown
//                            ->getOptionLabelFromRecordUsing(fn (Item $record) => "{$record->item_number} - {$record->description}")
//                            ->relationship('item', 'item_number')
//                            ->searchable()
//                            ->preload()
//                            ->required(fn (Get $get) => $get('type') === 'item')
//                            ->visible(fn (Get $get) => $get('type') === 'item')
//                            ->live()
//                            ->afterStateUpdated(function ($state, Set $set) {
//                                if (!$state) return;
//
//                                $item = Item::find($state);
//                                if ($item) {
//                                    // Automatically populate description and unit price
//                                    $set('description', $item->description);
//                                    $set('unit_price', $item->unit_price);
//                                }
//                            }),
//
//                        TextInput::make('description')
//                            ->required()
//                            ->columnSpan(fn (Get $get) => $get('type') === 'item' ? 1 : 2),
//                    ]),
//
//                Section::make('Pricing & Quantity')
//                    ->columns(4)
//                    ->schema([
//                        TextInput::make('quantity')
//                            ->numeric()
//                            ->default(1)
//                            ->required()
//                            ->minValue(0),
//
//                        TextInput::make('unit_of_measure')
//                            ->placeholder('e.g. PCS, KG'),
//
//                        TextInput::make('unit_price')
//                            ->numeric()
//                            ->prefix('$')
//                            ->required(),
//
//                        Select::make('location_id')
//                            ->relationship('location', 'name')
//                            ->placeholder('Select Location'),
//                    ]),
//
//                Section::make('Discounts & Tax')
//                    ->description('Calculations will be finalized upon saving.')
//                    ->columns(3)
//                    ->schema([
//                        TextInput::make('discount_percent')
//                            ->label('Discount %')
//                            ->numeric()
//                            ->default(0)
//                            ->suffix('%'),
//
//                        TextInput::make('vat_percent')
//                            ->label('VAT %')
//                            ->numeric()
//                            ->default(0)
//                            ->suffix('%'),
//
//                        Placeholder::make('calculation_note')
//                            ->label('Note')
//                            ->content('Totals are calculated automatically by the system upon saving.'),
//                    ]),
//            ]);
//    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                // Primary Column: Shows Item Number and Description
                TextColumn::make('item.item_number')
                    ->label('Item No.')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->description(fn(SalesInvoiceLine $record): ?string => $record->item?->description)
                    ->searchable(),

                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right'),

                TextColumn::make('unit_price')
                    ->money('USD')
                    ->alignment('right'),

                TextColumn::make('vat_amount')
                    ->label('VAT')
                    ->money('USD')
                    ->toggleable(),

                TextColumn::make('line_total')
                    ->label('Total')
                    ->money('USD')
                    ->weight('bold')
                    ->alignment('right'),
            ])
//            ->headerActions([
//                CreateAction::make()
//                    ->disabled(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->isPosted())
//                    ->label('Add Line'),
//            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn(SalesInvoiceLine $record) => $record->salesInvoice->isPosted()),
                DeleteAction::make()
                    ->hidden(fn(SalesInvoiceLine $record) => $record->salesInvoice->isPosted()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn(RelationManager $livewire) => $livewire->getOwnerRecord()->isPosted()),
                ]),
            ]);
    }
}
