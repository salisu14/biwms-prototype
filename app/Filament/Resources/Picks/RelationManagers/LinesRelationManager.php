<?php

declare(strict_types=1);

namespace App\Filament\Resources\Picks\RelationManagers;

use App\Enums\PickLineStatus;
use App\Models\Item;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Pick Lines';

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

                            return ($lastLine ?? 0) + 10000;
                        })
                        ->hint('Auto-generated (increments of 10,000)'),

                    Select::make('item_id')
                        ->relationship('item', 'item_code')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_code} - {$record->description}")
                        ->columnSpan(2)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (! $state) {
                                return;
                            }
                            $item = Item::find($state);
                            if ($item) {
                                $set('unit_of_measure_code', $item->base_unit_of_measure ?? 'PCS');
                                $set('description', $item->description);
                            }
                        }),
                ]),

                Section::make('Quantities')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('quantity')
                                ->label('Total Qty')
                                ->numeric()
                                ->required()
                                ->live(),

                            TextInput::make('quantity_to_handle')
                                ->label('Qty to Handle')
                                ->numeric()
                                ->required(),

                            TextInput::make('quantity_handled')
                                ->label('Qty Handled')
                                ->numeric()
                                ->default(0),

                            TextInput::make('unit_of_measure_code')
                                ->label('UOM')
                                ->required()
                                ->placeholder('PCS'),
                        ]),
                    ]),

                Section::make('Bin Movement')
                    ->description('Define source and destination bins for the pick.')
                    ->schema([
                        Grid::make(2)->schema([
                            Group::make([
                                Select::make('zone_id')
                                    ->label('From Zone')
                                    ->relationship('zone', 'zone_code')
                                    ->searchable()
                                    ->preload(),

                                Select::make('bin_id')
                                    ->label('From Bin')
                                    ->relationship('bin', 'bin_code')
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('lot_no')->label('Lot No.'),
                                TextInput::make('serial_no')->label('Serial No.'),
                                DatePicker::make('expiration_date')->label('Expiry Date'),
                            ])->columnSpan(1),

                            Group::make([
                                Select::make('destination_zone_id')
                                    ->label('To Zone')
                                    ->relationship('destinationZone', 'zone_code')
                                    ->searchable()
                                    ->preload(),

                                Select::make('destination_bin_id')
                                    ->label('To Bin')
                                    ->relationship('destinationBin', 'bin_code')
                                    ->searchable()
                                    ->preload(),
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
                    ->label('Qty to Handle')
                    ->numeric(4)
                    ->alignment('right'),

                TextColumn::make('quantity_handled')
                    ->label('Handled')
                    ->numeric(4)
                    ->color(fn ($record) => $record->line_status === PickLineStatus::COMPLETED ? 'success' : 'warning')
                    ->alignment('right'),

                TextColumn::make('bin.bin_code')
                    ->label('From Bin')
                    ->placeholder('-'),

                TextColumn::make('destinationBin.bin_code')
                    ->label('To Bin')
                    ->placeholder('-'),

                TextColumn::make('line_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (PickLineStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('line_status')
                    ->options(PickLineStatus::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['quantity_base'] = $data['quantity_to_handle'] ?? $data['quantity'] ?? 0;
                        $data['line_status'] = PickLineStatus::OPEN->value;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['quantity_base'] = $data['quantity_to_handle'] ?? $data['quantity'] ?? 0;

                        return $data;
                    }),
                DeleteAction::make(),
            ]);
    }
}
