<?php

namespace App\Filament\Resources\SalesShipmentHeaders\RelationManagers;

use App\Enums\SalesLineType;
use App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = SalesShipmentHeaderResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Select::make('type')
                            ->options(SalesLineType::class)
                            ->required()
                            ->live()
                            ->default(SalesLineType::Item),

                        TextInput::make('no')
                            ->label('No.')
                            ->required()
                            ->maxLength(20),

                        TextInput::make('line_no')
                            ->label('Line No.')
                            ->numeric()
                            ->default(fn ($livewire) => ($livewire->ownerRecord->lines()->max('line_no') ?? 0) + 10000)
                            ->disabled(),
                    ]),

                TextInput::make('description')
                    ->required()
                    ->maxLength(100)
                    ->columnSpanFull(),

                Grid::make(4)
                    ->schema([
                        TextInput::make('quantity')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->step(0.0001),

                        TextInput::make('unit_of_measure')
                            ->label('UoM')
                            ->maxLength(10),

                        TextInput::make('unit_price')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.0001),

                        TextInput::make('line_discount_pct')
                            ->label('Disc. %')
                            ->numeric()
                            ->suffix('%')
                            ->default(0),
                    ]),

                Section::make('Tracking & Logistics')
                    ->schema([
                        TextInput::make('location_code'),
                        TextInput::make('bin_code'),
                        TextInput::make('serial_no')
                            ->label('Serial No.'),
                        TextInput::make('lot_no')
                            ->label('Lot No.'),
                    ])->columns(2)->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('no')
            ->columns([
                TextColumn::make('line_no')
                    ->label('Line')
                    ->sortable(),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('no')
                    ->label('No.')
                    ->searchable(),

                TextColumn::make('description')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('quantity')
                    ->numeric(4)
                    ->alignRight(),

                TextColumn::make('unit_of_measure')
                    ->label('UoM'),

                TextColumn::make('qty_shipped_not_invoiced')
                    ->label('Shipped (Uninvoiced)')
                    ->numeric(4)
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('quantity_invoiced')
                    ->label('Invoiced')
                    ->numeric(4),

                TextColumn::make('unit_price')
                    ->money()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(SalesLineType::class),
                TernaryFilter::make('shipped_not_invoiced')
                    ->label('Pending Invoice')
                    ->queries(
                        true: fn (Builder $query) => $query->whereColumn('quantity', '>', 'quantity_invoiced'),
                        false: fn (Builder $query) => $query->whereColumn('quantity', '<=', 'quantity_invoiced'),
                    ),
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
