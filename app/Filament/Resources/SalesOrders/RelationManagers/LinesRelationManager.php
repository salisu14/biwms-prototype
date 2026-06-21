<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Enums\ItemType;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Services\VatService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = SalesOrderResource::class;

    protected static ?string $title = 'Order Lines';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Group::make([
                            Section::make()
                                ->schema([
                                    Select::make('item_id')
                                        ->label('Finished Good')
                                        ->relationship(
                                            'item',
                                            'description',
                                            fn (Builder $query) => $query->where('item_type', ItemType::FINISHED_GOOD)
                                        )
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            if (! $state) {
                                                return;
                                            }

                                            $item = Item::find($state);

                                            $defaultSalesUom = $item->uoms()
                                                ->wherePivot('uom_type', 'SALES')
                                                ->wherePivot('is_default', true)
                                                ->first();
                                            $defaultUomCode = $defaultSalesUom?->uom_code ?? $item->base_unit_of_measure;
                                            $conversionFactor = $item->getConversionFactorForUom($defaultUomCode);

                                            $set('item_code', $item->item_code);
                                            $set('description', $item->description);
                                            $set('unit_price', (float) $item->unit_price * $conversionFactor);
                                            $set('unit_cost', $item->unit_cost);
                                            $set('vat_product_posting_group_id', $item->vat_product_posting_group_id);

                                            // Resolve VAT percentage
                                            $vatBusGroup = $this->getOwnerRecord()->vat_business_posting_group_id;
                                            $vatProdGroup = $item->vat_product_posting_group_id;

                                            if ($vatBusGroup && $vatProdGroup) {
                                                $vatService = app(VatService::class);
                                                $percentage = $vatService->getVatPercentage($vatBusGroup, $vatProdGroup);
                                                $set('vat_percentage', $percentage);
                                            } else {
                                                $set('vat_percentage', 0);
                                            }

                                            // Set UOM from item's default sales UOM if available
                                            $set('unit_of_measure_code', $defaultUomCode);
                                            $set('qty_per_unit_of_measure', $conversionFactor);
                                        }),

                                    TextInput::make('description')
                                        ->required()
                                        ->columnSpan(2),

                                    TextInput::make('quantity')
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::calculateLine($set, $get)),

                                    Select::make('unit_of_measure_code')
                                        ->label('UOM')
                                        ->options(function (Get $get) {
                                            $itemId = $get('item_id');
                                            if (! $itemId) {
                                                return [];
                                            }

                                            $item = Item::find($itemId);
                                            if (! $item) {
                                                return [];
                                            }

                                            $uoms = $item->uoms()
                                                ->get()
                                                ->mapWithKeys(fn ($uom) => [
                                                    $uom->uom_code => $uom->uom_code,
                                                ])
                                                ->toArray();

                                            if (! array_key_exists($item->base_unit_of_measure, $uoms)) {
                                                $uoms[$item->base_unit_of_measure] = $item->base_unit_of_measure;
                                            }

                                            return $uoms;
                                        })
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            $itemId = $get('item_id');
                                            if (! $itemId) {
                                                return;
                                            }

                                            $item = Item::find($itemId);
                                            $conversionFactor = $item?->getConversionFactorForUom($state) ?? 1;
                                            $currentQtyPerUom = (float) ($get('qty_per_unit_of_measure') ?? 1);
                                            $currentUnitPrice = (float) ($get('unit_price') ?? 0);
                                            $baseUnitPrice = (float) ($item?->unit_price ?? 0);
                                            $expectedCurrentAutoPrice = $baseUnitPrice * $currentQtyPerUom;
                                            $isManualUnitPrice = abs($currentUnitPrice - $expectedCurrentAutoPrice) > 0.0001;

                                            $set('qty_per_unit_of_measure', $conversionFactor);
                                            if (! $isManualUnitPrice) {
                                                $set('unit_price', $baseUnitPrice * $conversionFactor);
                                            }
                                        }),

                                    TextInput::make('qty_per_unit_of_measure')
                                        ->label('Qty/UOM')
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated(),

                                    TextInput::make('unit_price')
                                        ->numeric()
                                        ->prefix('₦')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::calculateLine($set, $get)),

                                    TextInput::make('line_discount_percent')
                                        ->label('Disc %')
                                        ->numeric()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::calculateLine($set, $get)),
                                ])->columns(2),
                        ])->columnSpan(2),

                        Group::make([
                            Section::make('Line Totals')
                                ->schema([
                                    TextInput::make('line_amount')
                                        ->label('Net Amount')
                                        ->readOnly()
                                        ->numeric()
                                        ->prefix('₦'),

                                    TextInput::make('vat_amount')
                                        ->label('VAT')
                                        ->readOnly()
                                        ->numeric()
                                        ->prefix('₦'),

                                    TextInput::make('amount_including_vat')
                                        ->label('Total Incl. VAT')
                                        ->readOnly()
                                        ->numeric()
                                        ->prefix('₦')
                                        ->extraInputAttributes(['class' => 'font-bold text-primary-600']),
                                ]),

                            Section::make('Inventory')
                                ->schema([
                                    Select::make('location_id')
                                        ->relationship('location', 'name')
                                        ->default(fn ($get) => $get('../../location_id')), // Pull from parent order
                                    TextInput::make('bin_code'),
                                ])->collapsed(),
                        ])->columnSpan(1),
                    ]),

                Section::make('Technical Details')
                    ->schema([
                        TextInput::make('item_code')->readOnly(),
                        Select::make('vat_product_posting_group_id')
                            ->label('VAT Prod. Posting Group')
                            ->relationship('vatProductPostingGroup', 'code')
                            ->disabled()
                            ->dehydrated(),
                        Select::make('general_product_posting_group_id')
                            ->relationship('generalProductPostingGroup', 'id'),
                        Select::make('inventory_posting_group_id')
                            ->relationship('inventoryPostingGroup', 'id'),
                        TextInput::make('unit_cost')->numeric()->readOnly(),
                        Textarea::make('comment')->columnSpanFull(),
                    ])->columns(3)->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item_code')->label('Code')->sortable(),
                TextColumn::make('description')->searchable(),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right'),
                TextColumn::make('unit_of_measure_code')->label('UOM'),
                TextColumn::make('unit_price')
                    ->money()
                    ->alignment('right'),
                TextColumn::make('line_discount_percent')
                    ->label('Disc %')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('amount_including_vat')
                    ->label('Total')
                    ->money()
                    ->alignment('right')
                    ->weight('bold'),
                TextColumn::make('line_status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        /** @var SalesOrder $order */
                        $order = $this->getOwnerRecord();
                        $maxLineNumber = (int) ($order->lines()->max('line_number') ?? 0);
                        $data['line_number'] = $maxLineNumber + 10;

                        $qty = (float) ($data['quantity'] ?? 0);
                        $qtyPerUom = (float) ($data['qty_per_unit_of_measure'] ?? 1);
                        $data['quantity_base'] = $qty * ($qtyPerUom > 0 ? $qtyPerUom : 1);

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Helper to perform real-time line calculations in the UI
     */
    protected static function calculateLine(Set $set, Get $get): void
    {
        $qty = (float) $get('quantity');
        $price = (float) $get('unit_price');
        $discPercent = (float) $get('line_discount_percent');
        $vatPercent = (float) $get('vat_percentage');

        $subtotal = $qty * $price;
        $discountAmount = $subtotal * ($discPercent / 100);
        $netAmount = $subtotal - $discountAmount;
        $vatAmount = $netAmount * ($vatPercent / 100);
        $total = $netAmount + $vatAmount;

        $set('line_total', $subtotal);
        $set('line_discount_amount', $discountAmount);
        $set('line_amount', $netAmount);
        $set('vat_amount', $vatAmount);
        $set('amount_including_vat', $total);
    }
}
