<?php

namespace App\Filament\Resources\WarehouseReceipts\RelationManagers;

use App\Filament\Resources\WarehouseReceipts\WarehouseReceiptResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

    protected static ?string $relatedResource = WarehouseReceiptResource::class;

    protected static ?string $title = 'Receipt Lines';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('line_number')
                        ->numeric()
                        ->required(),
                    Select::make('item_id')
                        ->relationship('item', 'item_number')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(2),
                ]),

                Section::make('Quantities & UOM')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('quantity')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->live(),
                            TextInput::make('quantity_received')
                                ->label('Qty. Received')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->live(),
                            TextInput::make('quantity_outstanding')
                                ->label('Outstanding')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(),
                            TextInput::make('unit_of_measure_code')
                                ->label('UOM')
                                ->required(),
                        ]),
                    ]),

                Section::make('Warehouse Tracking & Bins')
                    ->columns(3)
                    ->schema([
                        TextInput::make('bin_code')->label('Bin'),
                        TextInput::make('lot_number')->label('Lot No.'),
                        TextInput::make('serial_number')->label('Serial No.'),
                        DatePicker::make('expiration_date'),
                        TextInput::make('zone_code')->label('Zone'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_number')
                    ->label('Line')
                    ->sortable(),
                TextColumn::make('item.item_number')
                    ->label('Item No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(30)
                    ->placeholder('No description'),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right'),
                TextColumn::make('quantity_received')
                    ->label('Received')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->color('success'),
                TextColumn::make('quantity_outstanding')
                    ->label('Outstanding')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                TextColumn::make('unit_of_measure_code')
                    ->label('UOM'),
                TextColumn::make('bin_code')
                    ->label('Bin')
                    ->toggleable(),
                TextColumn::make('lot_number')
                    ->label('Lot/Serial')
                    ->state(fn ($record) => $record->lot_number ?? $record->serial_number ?? '-')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('outstanding')
                    ->query(fn (Builder $query) => $query->where('quantity_outstanding', '>', 0)),
            ])
            ->headerActions([
                CreateAction::make(),
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
