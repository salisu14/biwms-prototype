<?php

namespace App\Filament\Resources\Bins\RelationManagers;

use App\Filament\Resources\Bins\BinResource;
use App\Models\BinContent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class ContentsRelationManager extends RelationManager
{
    protected static string $relationship = 'contents';

    protected static ?string $relatedResource = BinResource::class;

    protected static ?string $title = 'Inventory Contents';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Item Details')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('item_id')
                                ->relationship('item', 'item_code')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_code} - {$record->description}")
                                ->searchable()
                                ->preload()
                                ->required(),
                            TextInput::make('unit_of_measure_code')
                                ->label('UOM')
                                ->required(),
                        ]),
                    ]),

                Section::make('Tracking Information')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('lot_no')
                                ->label('Lot Number'),
                            TextInput::make('serial_no')
                                ->label('Serial Number'),
                            DatePicker::make('expiration_date'),
                        ]),
                    ]),

                Section::make('Quantities & Valuation')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->default(0),
                            TextInput::make('picked_quantity')
                                ->label('Reserved/Picked')
                                ->numeric()
                                ->default(0),
                            TextInput::make('negative_adj_qty')
                                ->label('Neg. Adjustment')
                                ->numeric()
                                ->default(0),
                            TextInput::make('unit_cost')
                                ->numeric()
                                ->prefix('$'),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('lot_no')
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Item No.')
                    ->description(fn ($record) => $record->item?->description)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tracking')
                    ->label('Tracking')
                    ->state(fn ($record) => $record->lot_no ?? $record->serial_no ?? '-')
                    ->description(fn ($record) => $record->expiration_date ? "Exp: " . $record->expiration_date->format('Y-m-d') : null)
                    ->toggleable(),

                TextColumn::make('quantity')
                    ->label('Total Qty')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->weight('bold'),

                TextColumn::make('available_qty')
                    ->label('Available')
                    ->state(fn (BinContent $record) => $record->availableQuantity())
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('picked_quantity')
                    ->label('Reserved')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->color('warning')
                    ->toggleable(),

                TextColumn::make('unit_of_measure_code')
                    ->label('UOM')
                    ->alignCenter(),

                TextColumn::make('unit_cost')
                    ->money()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // FIX: Explicitly set a default sort on a column that exists in bin_contents
            ->defaultSort('item_id', 'asc')
            ->filters([
                Filter::make('has_stock')
                    ->label('In Stock Only')
                    ->query(fn (Builder $query) => $query->where('quantity', '>', 0)),

                SelectFilter::make('item_id')
                    ->relationship('item', 'item_code', fn ($query) => $query->orderBy('item_code'))
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data) {
                        // Ensure the zone_id is pulled from the parent Bin record
                        $data['zone_id'] = $this->getOwnerRecord()->zone_id;
                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
