<?php

namespace App\Filament\Resources\InventoryPutaways\RelationManagers;

use App\Filament\Resources\InventoryPutaways\InventoryPutawayResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = InventoryPutawayResource::class;

    protected static ?string $title = 'Put-away Lines';
    protected static ?string $modelLabel = 'Line Item';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('line_no')
                        ->label('Line No.')
                        ->numeric()
                        ->required()
                        ->default(function () {
                            $lastLine = $this->getOwnerRecord()->lines()->max('line_no');
                            return ($lastLine ?? 0) + 10;
                        }),

                    Select::make('item_id')
                        ->relationship('item', 'item_number')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_number} - {$record->description}")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(2),
                ]),

                Section::make('Logistics & Quantities')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('bin_id')
                                ->label('Destination Bin')
                                ->relationship(
                                    name: 'bin',
                                    titleAttribute: 'bin_code',
                                    modifyQueryUsing: fn (Builder $query) => $query->where('location_id', $this->getOwnerRecord()->location_id)
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->bin_code} ({$record->zone?->zone_code})")
                                ->searchable()
                                ->preload()
                                ->required(),

                            TextInput::make('unit_of_measure')
                                ->label('UOM')
                                ->required()
                                ->placeholder('e.g., PCS, KG'),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn ($state, Set $set) => $set('qty_to_handle', $state)),

                            TextInput::make('qty_to_handle')
                                ->label('Qty. to Handle')
                                ->numeric()
                                ->default(0)
                                ->required(),

                            TextInput::make('qty_handled')
                                ->label('Qty. Handled')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('0.0000'),
                        ]),
                    ]),

                Section::make('Tracking')
                    ->collapsed()
                    ->schema([
                        KeyValue::make('item_tracking')
                            ->label('Lot & Serial Tracking')
                            ->keyLabel('Tracking Type')
                            ->valueLabel('Identifier'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_no')
                    ->label('Line')
                    ->sortable(),

                TextColumn::make('item.item_number')
                    ->label('Item No.')
                    ->description(fn ($record) => $record->item?->description)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('bin.bin_code')
                    ->label('Bin')
                    ->badge()
                    ->color('info')
                    ->placeholder('Undefined'),

                TextColumn::make('quantity')
                    ->label('Expected')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right'),

                TextColumn::make('qty_to_handle')
                    ->label('To Handle')
                    ->numeric(decimalPlaces: 4)
                    ->color('warning')
                    ->alignment('right'),

                TextColumn::make('qty_handled')
                    ->label('Handled')
                    ->numeric(decimalPlaces: 4)
                    ->color('success')
                    ->alignment('right'),

                TextColumn::make('unit_of_measure')
                    ->label('UOM')
                    ->alignCenter(),
            ])
            ->filters([
                Filter::make('outstanding')
                    ->query(fn (Builder $query) => $query->whereColumn('qty_handled', '<', 'quantity')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-m-plus-circle'),
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
