<?php

namespace App\Filament\Resources\ProductionBoms\RelationManagers;

use App\Filament\Resources\ProductionBoms\ProductionBomResource;
use App\Models\Item;
use App\Models\Manufacturing\ProductionBom;
use App\Models\UnitOfMeasure;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductionBomLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = ProductionBomResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Line Information')
                ->schema([

                    Grid::make(2)->schema([

                        TextInput::make('line_number')
                            ->numeric()
                            ->default(fn () => ($this->getOwnerRecord()->lines()->max('line_number') ?? 0) + 10000
                            )
                            ->required(),

                        Select::make('type')
                            ->options([
                                'ITEM' => 'Material Item',
                                'PRODUCTION_BOM' => 'Sub-Assembly',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, $set) => [
                                $set('item_id', null),
                                $set('production_bom_id_related', null),
                                $set('unit_of_measure_code', null),
                                $set('description', null),
                            ]),
                    ]),

                    // ✅ ITEM SELECT
                    Select::make('item_id')
                        ->label('Item')
                        ->relationship('item', 'item_code')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get) => $get('type') === 'ITEM')
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set) {
                            if (! $state) {
                                return;
                            }

                            $item = Item::with('uoms')->find($state);

                            $set('description', $item?->description);

                            // auto-set default UOM
                            $set('unit_of_measure_code', $item?->base_uom ?? null);
                        }),

                    // ✅ SUB BOM SELECT
                    Select::make('production_bom_id_related')
                        ->label('Sub BOM')
                        ->relationship('relatedBom', 'code')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get) => $get('type') === 'PRODUCTION_BOM')
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set) {
                            if (! $state) {
                                return;
                            }

                            $bom = ProductionBom::with('item')->find($state);

                            $set('description', $bom?->description);
                            $set('unit_of_measure_code', $bom?->unit_of_measure_code);
                        }),

                    TextInput::make('description')
                        ->columnSpanFull()
                        ->required(),

                    Grid::make(3)->schema([

                        TextInput::make('quantity_per')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0.00001),

                        TextInput::make('scrap_percent')
                            ->numeric()
                            ->default(0),

                        Select::make('unit_of_measure_code')
                            ->label('UOM')
                            ->disabled()
                            ->options(fn () => UnitOfMeasure::pluck('uom_code', 'uom_code')->toArray()), // 🔥 controlled automatically
                    ]),

                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([

                TextColumn::make('line_number')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'ITEM' => 'success',
                        'PRODUCTION_BOM' => 'warning',
                    }),

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('relatedBom.code')
                    ->label('Sub BOM')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('description')
                    ->limit(40),

                TextColumn::make('quantity_per')
                    ->numeric(),

                TextColumn::make('unit_of_measure_code')
                    ->label('UOM'),

                TextColumn::make('scrap_percent')
                    ->suffix('%'),

                TextColumn::make('routing_link_code')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
