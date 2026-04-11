<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Enums\PurchaseLineType;
use App\Enums\UomType;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Asset;
use App\Models\Item;
use App\Models\PurchaseOrder;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = PurchaseOrderResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('type')
                    ->label('Type')
                    ->options(PurchaseLineType::class)
                    ->required()
                    ->default(PurchaseLineType::ITEM)
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('item_id', null) && $set('asset_id', null)),

                Grid::make(4)->schema([
                    Select::make('item_id')
                        ->label('Item')
                        ->relationship('item', 'item_number', fn ($query) => $query->where('blocked', false))
                        ->searchable()
                        ->preload()
                        ->required(fn ($get) => $get('type') === PurchaseLineType::ITEM->value)
                        ->visible(fn ($get) => $get('type') === PurchaseLineType::ITEM->value)
                        ->lazy()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if (! $state) {
                                return;
                            }

                            $item = Item::find($state);
                            if ($item) {
                                $set('description', $item->description);
                                $set('item_code', $item->item_number);

                                // Logic to set the ACTIVE unit_of_measure field using Item model method
                                $baseUom = $item->getDefaultUom(UomType::BASE);
                                $set('unit_of_measure', $baseUom?->uom_code ?? '');

                                // Using the accessor from Item model
                                $set('unit_cost', $item->current_standard_cost ?? 0);
                            }
                        }),

                    Select::make('asset_id')
                        ->label('Asset')
                        ->relationship('asset', 'asset_no')
                        ->searchable()
                        ->preload()
                        ->required(fn ($get) => $get('type') === PurchaseLineType::FIXED_ASSET->value)
                        ->visible(fn ($get) => $get('type') === PurchaseLineType::FIXED_ASSET->value)
                        ->lazy()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (! $state) {
                                return;
                            }

                            $asset = Asset::find($state);
                            if ($asset) {
                                $set('description', $asset->description);
                                $set('item_code', $asset->asset_no);
                                $set('unit_of_measure', 'pcs'); // Default for assets
                                $set('unit_cost', 0); // User likely input cost on PO
                            }
                        }),

                    TextInput::make('quantity')
                        ->label('Quantity')
                        ->required()
                        ->numeric()
                        ->default(1)
                        ->live() // Using live to update previews immediately
                        ->step(0.0001),

                    Select::make('unit_of_measure')
                        ->label('Unit of Measure')
                        ->options([
                            'kg' => 'Kilogram',
                            'g' => 'Gram',
                            'ltr' => 'Litre',
                            'pcs' => 'Pieces',
                            'HOUR' => 'Hour',
                        ])
                        ->required()
                        ->searchable(),

                    TextInput::make('unit_cost')
                        ->label('Unit Cost')
                        ->required()
                        ->numeric()
                        ->live() // Using live to update previews immediately
                        ->step(0.0001),

                    TextInput::make('vat_percentage')
                        ->label('VAT %')
                        ->numeric()
                        ->default(0)
                        ->live()
                        ->columnSpan(2),
                ]),

                TextInput::make('description')
                    ->label('Description')
                    ->required()
                    ->columnSpanFull(),

                Grid::make(2)->schema([
                    TextInput::make('item_code')
                        ->label('Item Code')
                        ->disabled()
                        ->dehydrated(true),

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

                            return '$'.number_format($qty * $cost, 2);
                        }),

                    Placeholder::make('vat_amount_preview')
                        ->label('VAT Amount')
                        ->content(function (callable $get) {
                            $qty = (float) ($get('quantity') ?? 0);
                            $cost = (float) ($get('unit_cost') ?? 0);
                            $vatRate = (float) ($get('vat_percentage') ?? 0);
                            $lineTotal = $qty * $cost;
                            $vatAmount = $lineTotal * ($vatRate / 100);

                            return '$'.number_format($vatAmount, 2);
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

                            return '$'.number_format($grandTotal, 2);
                        })
                        ->extraAttributes(['class' => 'font-bold text-lg text-primary-600']),
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
                    ->label('Item Number')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('quantity')
                    ->numeric()
                    ->suffix(fn ($record) => ' '.($record->unit_of_measure ?? '')),

                TextColumn::make('unit_cost')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('line_total')
                    ->label('Subtotal')
                    ->money('USD')
                    ->summarize([
                        Sum::make()
                            ->money('USD'),
                    ]),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('USD')
                    ->summarize([
                        Sum::make()
                            ->money('USD'),
                    ]),

                IconColumn::make('is_fully_received')
                    ->label('Received')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn ($record): string => $record->is_fully_received ? 'Fully Received' : ($record->is_partially_received ? 'Partially Received' : 'Pending')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data, RelationManager $livewire): array {
                        /** @var PurchaseOrder $purchaseOrder */
                        $purchaseOrder = $livewire->getOwnerRecord();

                        // 1. Calculate sequential Line Number
                        $maxLineNumber = $purchaseOrder->lines()->max('line_number') ?? 0;
                        $data['line_number'] = $maxLineNumber + 1;

                        // 2. Mirroring Service Logic: Ensure item/asset metadata and Posting Groups are set
                        if ($data['type'] === PurchaseLineType::ITEM->value && isset($data['item_id'])) {
                            $item = Item::find($data['item_id']);
                            if ($item) {
                                $data['item_code'] = $item->item_number;
                                $data['general_product_posting_group_id'] = $item->general_product_posting_group_id;
                            }
                        } elseif ($data['type'] === PurchaseLineType::FIXED_ASSET->value && isset($data['asset_id'])) {
                            $asset = Asset::find($data['asset_id']);
                            if ($asset) {
                                $data['item_code'] = $asset->asset_no;
                            }
                        }

                        // 3. Pre-calculate totals for database storage (Calculated fields)
                        $qty = (float) ($data['quantity'] ?? 0);
                        $cost = (float) ($data['unit_cost'] ?? 0);
                        $vatRate = (float) ($data['vat_percentage'] ?? 0);

                        $data['line_total'] = $qty * $cost;
                        $data['vat_amount'] = $data['line_total'] * ($vatRate / 100);
                        $data['total_amount'] = $data['line_total'] + $data['vat_amount'];

                        return $data;
                    })
                    ->after(function (Model $record, RelationManager $livewire) {
                        /** @var PurchaseOrder $purchaseOrder */
                        $purchaseOrder = $livewire->getOwnerRecord();
                        $purchaseOrder->recalculateTotals();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        // Recalculate line totals on edit
                        $qty = (float) ($data['quantity'] ?? 0);
                        $cost = (float) ($data['unit_cost'] ?? 0);
                        $vatRate = (float) ($data['vat_percentage'] ?? 0);

                        $data['line_total'] = $qty * $cost;
                        $data['vat_amount'] = $data['line_total'] * ($vatRate / 100);
                        $data['total_amount'] = $data['line_total'] + $data['vat_amount'];

                        return $data;
                    })
                    ->after(function (Model $record, RelationManager $livewire) {
                        /** @var PurchaseOrder $purchaseOrder */
                        $purchaseOrder = $livewire->getOwnerRecord();
                        $purchaseOrder->recalculateTotals();
                    }),
                DeleteAction::make()
                    ->after(function (RelationManager $livewire) {
                        /** @var PurchaseOrder $purchaseOrder */
                        $purchaseOrder = $livewire->getOwnerRecord();
                        $purchaseOrder->recalculateTotals();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function (RelationManager $livewire) {
                            /** @var PurchaseOrder $purchaseOrder */
                            $purchaseOrder = $livewire->getOwnerRecord();
                            $purchaseOrder->recalculateTotals();
                        }),
                ]),
            ]);
    }
}
