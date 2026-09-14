<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Enums\ItemType;
use App\Enums\SalesLinePricingStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Services\Sales\SalesPricingResolver;
use App\Services\VatService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
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
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            if (! $state) {
                                                return;
                                            }

                                            $item = Item::find($state);
                                            if (! $item) {
                                                return;
                                            }

                                            /** @var SalesOrder $order */
                                            $order = $this->getOwnerRecord();

                                            $defaultSalesUom = $item->uoms()
                                                ->wherePivot('uom_type', 'SALES')
                                                ->wherePivot('is_default', true)
                                                ->first();
                                            $defaultUomCode = $defaultSalesUom?->uom_code ?? $item->base_unit_of_measure;
                                            $conversionFactor = $item->getConversionFactorForUom($defaultUomCode);

                                            // Commercial price comes from the canonical explicit-currency
                                            // resolver, never from the item-card reference value.
                                            $pricing = app(SalesPricingResolver::class)->resolveOrUnresolved(
                                                item: $item,
                                                customer: $order->customer,
                                                quantity: (float) ($get('quantity') ?? 1),
                                                variantCode: null,
                                                uom: $defaultUomCode,
                                                location: $order->location,
                                                documentCurrency: $order->currency_code,
                                            );

                                            $set('item_code', $item->item_code);
                                            $set('description', $item->description);
                                            $set('unit_cost', $item->unit_cost);
                                            $set('vat_code', $item->vatProductPostingGroup?->code);

                                            // Resolve VAT percentage
                                            $vatBusGroup = $order->vat_business_posting_group_id;
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

                                            self::applyResolvedPricing($set, $pricing);
                                            self::calculateLine($set, $get);
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
                                            if (! $item) {
                                                return;
                                            }

                                            $conversionFactor = $item->getConversionFactorForUom($state);
                                            $set('qty_per_unit_of_measure', $conversionFactor);

                                            // A deliberately manual price is never silently reverted to an
                                            // automatic price by a UOM change.
                                            if ($get('pricing_status') === SalesLinePricingStatus::MANUAL->value) {
                                                self::calculateLine($set, $get);

                                                return;
                                            }

                                            /** @var SalesOrder $order */
                                            $order = $this->getOwnerRecord();

                                            // Re-resolve through the same canonical resolver; never derive the
                                            // commercial price directly from the item-card reference value.
                                            $pricing = app(SalesPricingResolver::class)->resolveOrUnresolved(
                                                item: $item,
                                                customer: $order->customer,
                                                quantity: (float) ($get('quantity') ?? 1),
                                                variantCode: null,
                                                uom: $state,
                                                location: $order->location,
                                                documentCurrency: $order->currency_code,
                                            );

                                            self::applyResolvedPricing($set, $pricing);
                                            self::calculateLine($set, $get);
                                        }),

                                    TextInput::make('qty_per_unit_of_measure')
                                        ->label('Qty/UOM')
                                        ->numeric()
                                        ->readOnly()
                                        ->dehydrated(),

                                    TextInput::make('unit_price')
                                        ->numeric()
                                        ->prefix(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN'))
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                            // A user-entered price is an explicit manual commercial
                                            // price; any automatic provenance is dropped so it is
                                            // never falsely attributed to a SalesPrice/price list.
                                            $set('pricing_status', SalesLinePricingStatus::MANUAL->value);
                                            $set('price_source', null);
                                            $set('pricing_master_id', null);
                                            $set('price_record_id', null);

                                            self::calculateLine($set, $get);
                                        }),

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
                                        ->prefix(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN')),

                                    TextInput::make('vat_amount')
                                        ->label('VAT')
                                        ->readOnly()
                                        ->numeric()
                                        ->prefix(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN')),

                                    TextInput::make('amount_including_vat')
                                        ->label('Total Incl. VAT')
                                        ->readOnly()
                                        ->numeric()
                                        ->prefix(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN'))
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
                        Hidden::make('price_source')->dehydrated(),
                        Hidden::make('pricing_master_id')->dehydrated(),
                        Hidden::make('price_record_id')->dehydrated(),
                        Hidden::make('pricing_status')->dehydrated(),
                        TextInput::make('item_code')->readOnly(),
                        TextInput::make('vat_code')
                            ->label('VAT Prod. Posting Group')
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
                    ->money(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN'))
                    ->alignment('right'),
                TextColumn::make('line_discount_percent')
                    ->label('Disc %')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('amount_including_vat')
                    ->label('Total')
                    ->money(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN'))
                    ->alignment('right')
                    ->weight('bold'),
                TextColumn::make('pricing_status')
                    ->label('Pricing')
                    ->badge()
                    ->toggleable(),
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

                        // Guarantee the canonical resolver prices the line even when this
                        // create is driven programmatically and the interactive item/UOM
                        // hooks did not run. A positive price (manual or legacy) is never
                        // overwritten; a zero price always needs a trusted resolution.
                        $item = Item::query()->find($data['item_id'] ?? null);
                        $hasPositivePrice = (float) ($data['unit_price'] ?? 0) > 0;

                        if ($item && ! $hasPositivePrice) {
                            $pricing = app(SalesPricingResolver::class)->resolveOrUnresolved(
                                item: $item,
                                customer: $order->customer,
                                quantity: (float) ($data['quantity'] ?? 1),
                                variantCode: null,
                                uom: $data['unit_of_measure_code'] ?? $item->base_unit_of_measure,
                                location: $order->location,
                                documentCurrency: $order->currency_code,
                            );

                            $data['unit_price'] = $pricing['unit_price'];
                            $data['price_source'] = $pricing['price_source'];
                            $data['pricing_master_id'] = $pricing['pricing_master_id'];
                            $data['price_record_id'] = $pricing['price_record_id'];
                            $data['pricing_status'] = $pricing['pricing_status'];
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Persist the canonical resolver's price and provenance onto the line state.
     *
     * @param  array<string, mixed>  $pricing
     */
    protected static function applyResolvedPricing(Set $set, array $pricing): void
    {
        $set('unit_price', $pricing['unit_price']);
        $set('price_source', $pricing['price_source']);
        $set('pricing_master_id', $pricing['pricing_master_id']);
        $set('price_record_id', $pricing['price_record_id']);
        $set('pricing_status', $pricing['pricing_status']);
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
