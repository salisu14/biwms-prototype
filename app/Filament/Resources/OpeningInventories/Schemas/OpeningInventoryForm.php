<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories\Schemas;

use App\Models\Business;
use App\Models\Item;
use App\Models\Location;
use App\Models\OpeningInventory;
use App\Models\UnitOfMeasure;
use App\Services\NumberSeriesService;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class OpeningInventoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(12)->schema([
                Section::make('Opening Inventory Header')
                    ->columnSpan(12)
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema([
                        TextInput::make('document_number')
                            ->label('Document No.')
                            ->default(fn (): string => self::nextDocumentNumber())
                            ->required()
                            ->maxLength(50)
                            ->disabled(fn (?OpeningInventory $record): bool => $record?->status === OpeningInventory::STATUS_POSTED)
                            ->dehydrated()
                            ->helperText('Generated from number series when configured; manual values are validated per business.'),

                        Select::make('business_id')
                            ->label('Business')
                            ->relationship('business', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                            ->default(fn (): ?int => session('active_business_id') ?: Business::query()->where('is_active', true)->value('id'))
                            ->searchable()
                            ->preload()
                            ->disabled(fn (?OpeningInventory $record): bool => $record !== null)
                            ->dehydrated(),

                        DatePicker::make('posting_date')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->disabled(fn (?OpeningInventory $record): bool => $record?->status !== null && $record->status !== OpeningInventory::STATUS_DRAFT),

                        Select::make('source')
                            ->options([
                                'MANUAL' => 'Manual Entry',
                                'IMPORT' => 'Import',
                                'REPAIR_OPENING_STOCK' => 'Controlled Repair',
                                'SEED_OPENING_STOCK' => 'Seed Opening Stock',
                            ])
                            ->default('MANUAL')
                            ->required()
                            ->native(false)
                            ->disabled(fn (?OpeningInventory $record): bool => $record?->status !== null && $record->status !== OpeningInventory::STATUS_DRAFT),

                        TextInput::make('status')
                            ->default(OpeningInventory::STATUS_DRAFT)
                            ->disabled()
                            ->dehydrated(false),

                        Textarea::make('description')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->disabled(fn (?OpeningInventory $record): bool => $record?->status !== null && $record->status !== OpeningInventory::STATUS_DRAFT),
                    ]),

                Section::make('Opening Inventory Lines')
                    ->description('Enter counted opening quantities. Base quantity and value are previewed here and recalculated on save.')
                    ->columnSpan(12)
                    ->schema([
                        Repeater::make('lines')
                            ->label('Lines')
                            ->minItems(1)
                            ->addActionLabel('Add Item')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                                'xl' => 4,
                            ])
                            ->disabled(fn (?OpeningInventory $record): bool => $record?->status !== null && $record->status !== OpeningInventory::STATUS_DRAFT)
                            ->schema([
                                TextInput::make('id')->hidden()->dehydrated(),

                                Select::make('item_id')
                                    ->label('Item')
                                    ->options(fn (Get $get): array => self::itemOptions((int) ($get('../../business_id') ?? 0)))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                        $item = Item::query()->with('baseUom')->find($state);
                                        $set('unit_of_measure_id', $item?->base_uom_id);
                                        $set('unit_cost', $item?->unit_cost ?? '0.00000000');
                                        self::recalculateLine($set, $get);
                                    }),

                                Select::make('location_id')
                                    ->label('Location')
                                    ->options(fn (Get $get): array => self::locationOptions((int) ($get('../../business_id') ?? 0)))
                                    ->searchable()
                                    ->required(),

                                Select::make('unit_of_measure_id')
                                    ->label('UOM')
                                    ->options(fn (Get $get): array => self::uomOptions((int) ($get('item_id') ?? 0)))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, Get $get): mixed => self::recalculateLine($set, $get)),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.00000001)
                                    ->step('0.00000001')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get): mixed => self::recalculateLine($set, $get)),

                                TextInput::make('quantity_base')
                                    ->label('Base Qty')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('unit_cost')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.00000001)
                                    ->step('0.00000001')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get): mixed => self::recalculateLine($set, $get)),

                                TextInput::make('amount')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('lot_number')
                                    ->maxLength(50),

                                TextInput::make('serial_number')
                                    ->maxLength(50)
                                    ->distinct(),
                            ]),
                    ]),
            ]),
        ]);
    }

    public static function nextDocumentNumber(): string
    {
        $service = app(NumberSeriesService::class);

        foreach (['OPENING-INVENTORY', 'OPEN-INV', 'OPENING'] as $seriesCode) {
            $next = $service->tryGetNextNo($seriesCode);
            if ($next !== null) {
                return $next;
            }
        }

        return 'OPEN-'.now()->format('YmdHis');
    }

    /**
     * @return array<int, string>
     */
    private static function itemOptions(int $businessId): array
    {
        return Item::query()
            ->when(DatabaseSchema::hasColumn('items', 'business_id') && $businessId > 0, fn (Builder $query): Builder => $query->where(function (Builder $query) use ($businessId): void {
                $query->whereNull('business_id')->orWhere('business_id', $businessId);
            }))
            ->orderBy('item_code')
            ->limit(250)
            ->get()
            ->mapWithKeys(fn (Item $item): array => [$item->id => "{$item->item_code} - {$item->description}"])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function locationOptions(int $businessId): array
    {
        return Location::query()
            ->when(DatabaseSchema::hasColumn('locations', 'business_id') && $businessId > 0, fn (Builder $query): Builder => $query->where(function (Builder $query) use ($businessId): void {
                $query->whereNull('business_id')->orWhere('business_id', $businessId);
            }))
            ->orderBy('code')
            ->limit(250)
            ->get()
            ->mapWithKeys(fn (Location $location): array => [$location->id => "{$location->code} - {$location->name}"])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function uomOptions(int $itemId): array
    {
        $item = Item::query()->with(['baseUom', 'uoms'])->find($itemId);

        if (! $item) {
            return UnitOfMeasure::query()->orderBy('uom_code')->pluck('uom_code', 'id')->all();
        }

        return collect([$item->baseUom])
            ->merge($item->uoms)
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn (UnitOfMeasure $uom): array => [$uom->id => $uom->uom_code])
            ->all();
    }

    private static function recalculateLine(Set $set, Get $get): void
    {
        $item = Item::query()->with(['baseUom', 'uoms'])->find($get('item_id'));
        $uom = UnitOfMeasure::query()->find($get('unit_of_measure_id'));
        $quantity = DecimalMath::quantity($get('quantity') ?: 0);
        $unitCost = DecimalMath::unitCost($get('unit_cost') ?: 0);
        $quantityBase = $quantity;

        if ($item && $uom && $uom->id !== $item->base_uom_id) {
            $quantityBase = DecimalMath::mul($quantity, $item->getConversionFactorForUomDecimal($uom->uom_code), DecimalPrecision::QUANTITY_SCALE);
        }

        $set('quantity_base', $quantityBase);
        $set('amount', DecimalMath::amount(DecimalMath::mul($quantityBase, $unitCost, DecimalPrecision::AMOUNT_SCALE)));
    }
}
