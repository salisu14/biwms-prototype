<?php

namespace App\Filament\Resources\WarehouseActivities\RelationManagers;

use App\Filament\Resources\WarehouseActivities\WarehouseActivityResource;
use App\Models\WarehouseActivityLine;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
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

    protected static ?string $relatedResource = WarehouseActivityResource::class;

    protected static ?string $title = 'Activity Detail Lines';

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
                            // Automatically suggest the next line number (Max + 10)
                            $lastLine = $this->getOwnerRecord()->lines()->max('line_no');
                            return ($lastLine ?? 0) + 10;
                        })
                        ->hint('Auto-generated (increments of 10)'),

                    Select::make('item_id')
                        ->relationship('item', 'item_code')
                        ->getOptionLabelFromRecordUsing(fn($record) => "{$record->item_code} - {$record->description}")
                        ->columnSpan(2)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if (!$state) return;
                            $item = \App\Models\Item::find($state);
                            if ($item) {
                                $set('unit_of_measure_code', $item->base_unit_of_measure ?? 'PCS');
                            }
                        }),
                ]),

                Section::make('Quantities & Unit of Measure')
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
                            TextInput::make('unit_of_measure_code')
                                ->label('UOM')
                                ->required()
                                ->placeholder('e.g., PCS, KG'),
                        ]),
                        Hidden::make('quantity_base')
                            ->dehydrated(),
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
                    ->description(fn($record) => $record->item?->description)
                    ->searchable(),

                TextColumn::make('quantity_to_handle')
                    ->label('Target')
                    ->numeric(4)
                    ->alignment('right'),

                TextColumn::make('quantity_handled')
                    ->label('Handled')
                    ->numeric(4)
                    ->color(fn($record) => $record->isComplete() ? 'success' : 'warning')
                    ->alignment('right'),

                TextColumn::make('unit_of_measure_code')
                    ->label('UOM')
                    ->alignCenter(),

                TextColumn::make('line_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['quantity_base'] = $data['quantity_to_handle'];
                        $data['line_status'] = 'open';
                        return $data;
                    }),
            ])
            ->recordActions([
                // This View Action acts as the "Nested" display
                ViewAction::make()
                    ->modalHeading('Activity Line Ledger Details')
                    ->modalWidth('6xl')
                    ->schema(fn(Schema $infolist) => $this->getLineInfolist($infolist)),

                EditAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['quantity_base'] = $data['quantity_to_handle'];
                        return $data;
                    }),
                DeleteAction::make(),
            ]);
    }

    /**
     * This defines the infolist shown when viewing a line.
     * It acts as a nested display for Warehouse Entries.
     */
    public function getLineInfolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Line Execution Summary')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('line_no')->label('Line #'),
                            TextEntry::make('line_status')->badge(),
                            TextEntry::make('quantity_to_handle')->label('Target Qty')->numeric(4),
                            TextEntry::make('quantity_handled')->label('Processed Qty')->numeric(4)->color('success'),
                        ]),
                    ]),

                // This is the "Nested" part: Showing the entries generated by this line
                Section::make('Warehouse Ledger Entries')
                    ->description('Historical stock movements recorded for this specific activity line.')
                    ->schema([
                        RepeatableEntry::make('warehouseEntries') // Assumes relationship on model
                        ->label('')
                            ->schema([
                                Grid::make(6)->schema([
                                    TextEntry::make('entry_timestamp')
                                        ->label('Timestamp')
                                        ->dateTime()
                                        ->size('sm'),
                                    TextEntry::make('entry_type')
                                        ->badge()
                                        ->color(fn($state) => $state === 'positive' ? 'success' : 'danger'),
                                    TextEntry::make('quantity')
                                        ->numeric(4)
                                        ->weight('bold'),
                                    TextEntry::make('bin.bin_code')
                                        ->label('Bin')
                                        ->icon('heroicon-m-map-pin'),
                                    TextEntry::make('lot_no')
                                        ->label('Lot/Serial')
                                        ->placeholder('-'),
                                    TextEntry::make('created_by')
                                        ->label('User ID'),
                                ]),
                            ])
                            ->grid(1)
                            ->placeholder('No ledger entries found for this task.'),
                    ]),
            ]);
    }
}
