<?php

namespace App\Filament\Resources\SalesOrders\RelationManagers;

use App\Enums\ItemType;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\Item;
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
                                            $set('item_code', $item->item_code);
                                            $set('description', $item->description);
                                            $set('unit_price', $item->unit_price);
                                            $set('unit_cost', $item->unit_cost);
                                            $set('vat_percentage', 15); // Default or fetch from item

                                            // Set UOM from item's default sales UOM if available
                                            $uom = $item->uoms()->wherePivot('uom_type', 'SALES')->first()
                                                ?? $item->uoms()->wherePivot('uom_type', 'BASE')->first();

                                            if ($uom) {
                                                $set('unit_of_measure_code', $uom->uom_code);
                                            }
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

                                    TextInput::make('unit_of_measure_code')
                                        ->label('UOM')
                                        ->disabled()
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
                    ->mutateDataUsing(function (array $data) {
                        $data['line_number'] = rand(1000, 9999); // Logic to increment based on existing lines

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
        $vatPercent = (float) $get('vat_percentage') ?? 15;

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
