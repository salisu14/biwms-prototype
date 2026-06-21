<?php

namespace App\Filament\Resources\WarehouseShipments\RelationManagers;

use App\Filament\Resources\WarehouseShipments\WarehouseShipmentResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = WarehouseShipmentResource::class;

    protected static ?string $title = 'Shipment Line Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('line_number')
                        ->label('Line No.')
                        ->numeric()
                        ->required(),

                    Select::make('item_id')
                        ->relationship('item', 'item_number')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_number} - {$record->description}")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(2),
                ]),

                Section::make('Quantities & Fulfillment')
                    ->description('Track the progress of this line from order to shipment.')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('quantity')
                                ->label('Order Quantity')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->live(),

                            TextInput::make('quantity_picked')
                                ->label('Qty. Picked')
                                ->numeric()
                                ->default(0)
                                ->helperText('Quantity currently in packing area.')
                                ->required()
                                ->live(),

                            TextInput::make('quantity_shipped')
                                ->label('Qty. Shipped')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->live(),

                            TextInput::make('quantity_outstanding')
                                ->label('Outstanding')
                                ->numeric()
                                ->disabled()
                                ->dehydrated()
                                ->extraInputAttributes(['class' => 'font-bold text-primary-600']),
                        ]),
                    ]),

                Section::make('Logistics & Tracking')
                    ->columns(3)
                    ->schema([
                        TextInput::make('unit_of_measure_code')
                            ->label('UOM')
                            ->required(),
                        TextInput::make('zone_code')
                            ->label('Warehouse Zone'),
                        TextInput::make('bin_code')
                            ->label('Pick Bin'),

                        TextInput::make('lot_number')
                            ->label('Lot Number'),
                        TextInput::make('serial_number')
                            ->label('Serial Number'),
                        TextInput::make('source_line_id')
                            ->label('Source Line Ref')
                            ->numeric()
                            ->disabled(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_number')
                    ->label('Line')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('item.item_number')
                    ->label('Item No.')
                    ->description(fn ($record) => $record->description)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Target')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->weight('bold'),

                TextColumn::make('quantity_picked')
                    ->label('Picked')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->color(fn ($record) => $record->isFullyPicked() ? 'success' : 'info')
                    ->icon(fn ($record) => $record->isFullyPicked() ? 'heroicon-m-check-circle' : 'heroicon-m-hand-raised'),

                TextColumn::make('quantity_shipped')
                    ->label('Shipped')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->color('success'),

                TextColumn::make('quantity_outstanding')
                    ->label('Outstanding')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                TextColumn::make('bin_code')
                    ->label('Bin')
                    ->placeholder('N/A')
                    ->toggleable(),

                TextColumn::make('lot_number')
                    ->label('Lot/Serial')
                    ->state(fn ($record) => $record->lot_number ?? $record->serial_number ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('line_number')
            ->filters([
                Filter::make('needs_picking')
                    ->label('Needs Picking')
                    ->query(fn (Builder $query) => $query->whereColumn('quantity_picked', '<', 'quantity')),

                Filter::make('outstanding')
                    ->label('Pending Shipment')
                    ->query(fn (Builder $query) => $query->where('quantity_outstanding', '>', 0)),
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
