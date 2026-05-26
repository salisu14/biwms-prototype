<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use App\Enums\ItemType;
use App\Enums\PurchaseLineType;
use App\Enums\UomType;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\FixedAsset;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Services\VatService;
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
use Illuminate\Validation\ValidationException;

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
                    ->afterStateUpdated(fn ($set) => $set('item_id', null) && $set('item_code', null) && $set('asset_id', null)),

                Grid::make(4)->schema([
                    // HIDDEN: The original ID selector (now handled by item_code dropdown below)
                    // Select::make('item_id')
                    //    ->label('Item')
                    //    ->relationship('item', 'item_code', fn ($query) => $query->where('blocked', false))
                    //    ->searchable()
                    //    ->preload()
                    //    ...

                    // FIXED: Converted to Select, filters RAW_MATERIALS, and sets item_id automatically
                    Select::make('item_code')
                        ->label('Item Code')
                        ->relationship(
                            name: 'item',
                            titleAttribute: 'item_code',
                            modifyQueryUsing: fn ($query) => $query
                                ->where('blocked', false)
                                ->where('item_type', ItemType::RAW_MATERIAL->value)
                        )
                        ->searchable()
                        ->preload()
                        ->required(fn ($get) => $get('type') === PurchaseLineType::ITEM->value)
                        ->visible(fn ($get) => $get('type') === PurchaseLineType::ITEM->value)
                        ->dehydrated(false) // Prevent saving ID to the 'item_code' string column
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if (! $state) {
                                return;
                            }

                            $item = Item::find($state);
                            if ($item) {
                                // 1. Set the hidden Database ID (Fixes SQL Not Null constraint)
                                $set('item_id', $item->id);

                                // 2. Set the Code string for display/storage
                                $set('item_code', $item->item_code);

                                // 3. Set Description
                                $set('description', $item->description);

                                // 4. Set VAT Group
                                $set('vat_product_posting_group_id', $item->vat_product_posting_group_id);

                                // 5. Resolve VAT percentage
                                $vatBusGroup = $this->getOwnerRecord()->vat_business_posting_group_id;
                                $vatProdGroup = $item->vat_product_posting_group_id;

                                if ($vatBusGroup && $vatProdGroup) {
                                    $vatService = app(VatService::class);
                                    $percentage = $vatService->getVatPercentage($vatBusGroup, $vatProdGroup);
                                    $set('vat_percentage', $percentage);
                                } else {
                                    $set('vat_percentage', 0);
                                }

                                // 6. Set UOM
                                $baseUom = $item->getDefaultUom(UomType::BASE);
                                $set('unit_of_measure', $baseUom?->uom_code ?? '');

                                // 7. Set Cost
                                $set('unit_cost', $item->current_standard_cost ?? 0);
                            }
                        }),

                    Select::make('asset_id')
                        ->label('Fixed Asset')
                        ->relationship('asset', 'fa_no')
                        ->searchable()
                        ->preload()
                        ->required(fn ($get) => $get('type') === PurchaseLineType::FIXED_ASSET->value)
                        ->visible(fn ($get) => $get('type') === PurchaseLineType::FIXED_ASSET->value)
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (! $state) {
                                return;
                            }

                            $asset = FixedAsset::find($state);
                            if ($asset) {
                                $set('description', $asset->description);
                                $set('item_code', $asset->fa_no);
                                $set('unit_of_measure', 'pcs');
                                $set('unit_cost', 0);
                            }
                        }),

                    TextInput::make('quantity')
                        ->label('Quantity')
                        ->required()
                        ->numeric()
                        ->default(1)
                        ->live()
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
                        ->live()
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
                    // This field now holds the display value set by the dropdown
                    TextInput::make('item_code')
                        ->label('Item Code')
                        // Disabled allows user to see it but not edit it directly
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
                    ->label('Ordered')
                    ->numeric()
                    ->suffix(fn ($record) => ' '.($record->unit_of_measure ?? '')),

                TextColumn::make('received_quantity')
                    ->label('Received')
                    ->numeric()
                    ->suffix(fn ($record) => ' '.($record->unit_of_measure ?? ''))
                    ->color('success'),

                TextColumn::make('remaining_quantity')
                    ->label('Remaining')
                    ->state(fn ($record) => $record->remaining_quantity)
                    ->numeric()
                    ->suffix(fn ($record) => ' '.($record->unit_of_measure ?? ''))
                    ->color('warning'),

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
                        // 1. Server-Side Validation
                        if ($data['type'] === PurchaseLineType::ITEM->value && empty($data['item_id'])) {
                            throw ValidationException::withMessages([
                                'item_id' => 'The Item field is required when Type is Item.',
                            ]);
                        }

                        if ($data['type'] === PurchaseLineType::FIXED_ASSET->value && empty($data['asset_id'])) {
                            throw ValidationException::withMessages([
                                'asset_id' => 'The Fixed Asset field is required when Type is Fixed Asset.',
                            ]);
                        }

                        /** @var PurchaseOrder $purchaseOrder */
                        $purchaseOrder = $livewire->getOwnerRecord();

                        // 2. Calculate sequential Line Number
                        $maxLineNumber = $purchaseOrder->lines()->max('line_number') ?? 0;
                        $data['line_number'] = $maxLineNumber + 1;

                        // 3. Mirroring Service Logic
                        if ($data['type'] === PurchaseLineType::ITEM->value && isset($data['item_id'])) {
                            $item = Item::find($data['item_id']);
                            if ($item) {
                                $data['item_code'] = $item->item_code;
                                $data['general_product_posting_group_id'] = $item->general_product_posting_group_id;
                            }
                        } elseif ($data['type'] === PurchaseLineType::FIXED_ASSET->value && isset($data['asset_id'])) {
                            $asset = FixedAsset::find($data['asset_id']);
                            if ($asset) {
                                $data['item_code'] = $asset->fa_no;
                            }
                        }

                        // 4. Pre-calculate totals
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
