<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\ItemMaster;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseOrderLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Order Lines';

    protected static ?string $relatedResource = PurchaseOrderResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(4)->schema([
                    Select::make('item_id')
                        ->label('Item')
                        ->relationship('item', 'item_code')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->lazy()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if (!$state) {
                                return;
                            }

                            $item = ItemMaster::find($state);
                            if ($item) {
                                $set('description', $item->description);
                                $set('item_code', $item->item_code);

                                // FIX: Logic to set the ACTIVE unit_of_measure field
                                $baseUom = $item->getDefaultUom(\App\Enums\UomType::BASE);
                                $set('unit_of_measure', $baseUom?->uom_code ?? '');

                                $set('unit_cost', $item->current_standard_cost ?? 0);

                                // Trigger previews
                                $set('line_total_preview', ($get('quantity') ?? 0) * ($get('unit_cost') ?? 0));
                            }
                        }),

                    TextInput::make('quantity')
                        ->label('Quantity')
                        ->required()
                        ->numeric()
                        ->step(0.0001)
                        ->lazy()
                        ->debounce(500),

                    // FIX: Re-adding the ACTIVE Unit of Measure Select here
                    Select::make('unit_of_measure')
                        ->label('Unit of Measure')
                        ->options([
                            'kg' => 'Kilogram',
                            'g' => 'Gram',
                            'ltr' => 'Litre',
                            'pcs' => 'Pieces',
                        ])
                        ->required()
                        ->searchable(),

                    TextInput::make('unit_cost')
                        ->label('Unit Cost')
                        ->required()
                        ->numeric()
                        ->step(0.0001)
                        ->lazy()
                        ->debounce(500),

                    // Moved VAT to the second row of the grid to make space
                    TextInput::make('vat_percentage')
                        ->label('VAT %')
                        ->numeric()
                        ->default(0)
                        ->lazy()
                        ->debounce(500)
                        ->columnSpan(2), // Make it span 2 columns for balance
                ]),

                // Description
                TextInput::make('description')
                    ->label('Description')
                    ->required()
                    ->columnSpanFull(),

                Grid::make(2)->schema([
                    TextInput::make('item_code')
                        ->label('Item Code')
                        ->disabled()
                        ->dehydrated(false), // Keep disabled, not saving, just display

                    DatePicker::make('expected_delivery_date')
                        ->label('Expected Delivery')
                        ->native(false),
                ]),

                // Visual Helpers
                Grid::make(3)->schema([
                    Placeholder::make('line_total_preview')
                        ->label('Line Total (Excl. VAT)')
                        ->content(function (callable $get) {
                            $qty = (float) ($get('quantity') ?? 0);
                            $cost = (float) ($get('unit_cost') ?? 0);
                            return '$' . number_format($qty * $cost, 2);
                        }),

                    Placeholder::make('vat_amount_preview')
                        ->label('VAT Amount')
                        ->content(function (callable $get) {
                            $qty = (float) ($get('quantity') ?? 0);
                            $cost = (float) ($get('unit_cost') ?? 0);
                            $vatRate = (float) ($get('vat_percentage') ?? 0);
                            $lineTotal = $qty * $cost;
                            $vatAmount = $lineTotal * ($vatRate / 100);
                            return '$' . number_format($vatAmount, 2);
                        }),

                    Placeholder::make('total_amount_preview')
                        ->label('Total Line Value')
                        ->content(function (callable $get) {
                            $qty = (float) ($get('quantity') ?? 0);
                            $cost = (float) ($get('unit_cost') ?? 0);
                            $vatRate = (float) ($get('vat_percentage') ?? 0);
                            $lineTotal = $qty * $cost;
                            $vatAmount = $lineTotal * ($vatRate / 100);
                            $grandTotal = $lineTotal + $vatAmount;
                            return '$' . number_format($grandTotal, 2);
                        })
                        ->extraAttributes(['class' => 'font-bold text-lg']),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('line_number', 'asc')
            ->modifyQueryUsing(fn ($query) => $query->with('item'))
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('line_number')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('quantity')
                    ->numeric()
                    ->suffix(fn($record) => $record->unit_of_measure ?? ''),

                TextColumn::make('unit_cost')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('line_total')
                    ->label('Subtotal')
                    ->money('USD')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD'),
                    ]),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('USD')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD'),
                    ]),

                Tables\Columns\IconColumn::make('is_fully_received')
                    ->label('Received')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn($record): string => $record->is_fully_received ? 'Fully Received' : ($record->is_partially_received ? 'Partially Received' : 'Pending')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data, RelationManager $livewire): array {
                        $purchaseOrder = $livewire->getOwnerRecord();

                        // Line number
                        $maxLineNumber = $purchaseOrder->lines()->max('line_number') ?? 0;
                        $data['line_number'] = $maxLineNumber + 1;

                        // Ensure item_code is set
                        if (!isset($data['item_code']) && isset($data['item_id'])) {
                            $item = ItemMaster::find($data['item_id']);
                            if ($item) {
                                $data['item_code'] = $item->item_code;
                            }
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
