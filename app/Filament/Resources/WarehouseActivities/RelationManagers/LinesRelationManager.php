<?php

namespace App\Filament\Resources\WarehouseActivities\RelationManagers;

use App\Filament\Resources\WarehouseActivities\WarehouseActivityResource;
use App\Models\WarehouseActivityLine;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = WarehouseActivityResource::class;

    protected static ?string $title = 'Activity Detail Lines';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('line_no')
                        ->numeric()
                        ->required(),
                    Select::make('item_id')
                        ->relationship('item', 'item_code')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_code} - {$record->description}")
                        ->columnSpan(2)
                        ->required(),
                ]),

                Section::make('Quantities')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('quantity_to_handle')
                                ->label('Target Qty')
                                ->numeric()
                                ->required()
                                ->live(),
                            TextInput::make('quantity_handled')
                                ->label('Qty Handled')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->live(),
                            TextInput::make('line_status')
                                ->disabled()
                                ->dehydrated(),
                        ]),
                    ]),

                Section::make('Logistics Workflow')
                    ->description('Define source and destination for item movement.')
                    ->schema([
                        Grid::make(2)->schema([
                            Group::make([
                                Placeholder::make('from_header')->content('Source (Take)'),
                                Select::make('source_zone_id')->relationship('sourceZone', 'zone_code')->label('Zone'),
                                Select::make('source_bin_id')->relationship('sourceBin', 'bin_code')->label('Bin'),
                                TextInput::make('source_lot_no')->label('Source Lot'),
                            ])->columnSpan(1),

                            Group::make([
                                Placeholder::make('to_header')->content('Destination (Place)'),
                                Select::make('destination_zone_id')->relationship('destinationZone', 'zone_code')->label('Zone'),
                                Select::make('destination_bin_id')->relationship('destinationBin', 'bin_code')->label('Bin'),
                                TextInput::make('destination_lot_no')->label('Dest. Lot'),
                            ])->columnSpan(1),
                        ]),
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

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->description(fn ($record) => $record->item?->description)
                    ->searchable(),

                TextColumn::make('quantity_to_handle')
                    ->label('Target')
                    ->numeric(4)
                    ->alignment('right'),

                TextColumn::make('quantity_handled')
                    ->label('Handled')
                    ->numeric(4)
                    ->color(fn ($record) => $record->isComplete() ? 'success' : 'warning')
                    ->alignment('right'),

                TextColumn::make('movement')
                    ->label('Movement (From -> To)')
                    ->state(fn (WarehouseActivityLine $record) =>
                        ($record->sourceBin?->bin_code ?? 'NA') . ' → ' . ($record->destinationBin?->bin_code ?? 'NA')
                    )
                    ->icon('heroicon-m-arrows-right-left'),

                TextColumn::make('line_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('line_status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ]),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
