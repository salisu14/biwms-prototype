<?php

namespace App\Filament\Resources\WarehouseJournalBatches\RelationManagers;

use App\Models\Item;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Journal Lines';

    protected static ?string $recordTitleAttribute = 'line_no';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Line Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Entry')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('line_no')
                                        ->label('Line No.')
                                        ->numeric()
                                        ->required()
                                        ->step(10000),

                                    DatePicker::make('posting_date')
                                        ->label('Posting Date')
                                        ->required()
                                        ->native(false),

                                    Select::make('entry_type')
                                        ->label('Entry Type')
                                        ->required()
                                        ->native(false)
                                        ->options([
                                            'pick' => 'Pick',
                                            'put_away' => 'Put-Away',
                                            'movement' => 'Movement',
                                            'positive_adj' => 'Positive Adjustment',
                                            'negative_adj' => 'Negative Adjustment',
                                            'physical_inventory' => 'Physical Inventory',
                                        ]),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('item_id')
                                        ->label('Item No.')
                                        ->relationship('item', 'description')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->getOptionLabelFromRecordUsing(fn (Item $record) => "{$record->no} - {$record->description}"),

                                    Textarea::make('description')
                                        ->rows(1),
                                ]),

                                Grid::make(3)->schema([
                                    TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->required(),

                                    TextInput::make('unit_of_measure_code')
                                        ->label('UOM')
                                        ->maxLength(20),

                                    TextInput::make('document_no')
                                        ->label('Document No.')
                                        ->maxLength(50),
                                ]),
                            ]),

                        Tabs\Tab::make('Source (Take From)')
                            ->icon('heroicon-o-arrow-up-tray')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('source_location_id')
                                        ->label('Location')
                                        ->relationship('sourceLocation', 'name')
                                        ->required()
                                        ->searchable()
                                        ->preload(),

                                    Select::make('source_zone_id')
                                        ->label('Zone')
                                        ->relationship('sourceZone', 'code')
                                        ->searchable()
                                        ->preload(),

                                    Select::make('source_bin_id')
                                        ->label('Bin')
                                        ->relationship('sourceBin', 'code')
                                        ->searchable()
                                        ->preload(),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('source_lot_no')
                                        ->label('Lot No.')
                                        ->maxLength(50),

                                    TextInput::make('source_serial_no')
                                        ->label('Serial No.')
                                        ->maxLength(50),
                                ]),
                            ]),

                        Tabs\Tab::make('Destination (Place To)')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('destination_location_id')
                                        ->label('Location')
                                        ->relationship('destinationLocation', 'name')
                                        ->searchable()
                                        ->preload(),

                                    Select::make('destination_zone_id')
                                        ->label('Zone')
                                        ->relationship('destinationZone', 'code')
                                        ->searchable()
                                        ->preload(),

                                    Select::make('destination_bin_id')
                                        ->label('Bin')
                                        ->relationship('destinationBin', 'code')
                                        ->searchable()
                                        ->preload(),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('destination_lot_no')
                                        ->label('Lot No.')
                                        ->maxLength(50),

                                    TextInput::make('destination_serial_no')
                                        ->label('Serial No.')
                                        ->maxLength(50),
                                ]),
                            ]),

                        Tabs\Tab::make('Phys. Inventory')
                            ->icon('heroicon-o-clipboard')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('qty_calculated')
                                        ->label('Qty (Calculated)')
                                        ->numeric()
                                        ->readOnly()
                                        ->helperText('System-expected quantity.'),

                                    TextInput::make('qty_physical')
                                        ->label('Qty (Physical Count)')
                                        ->numeric()
                                        ->helperText('User-entered counted quantity.'),

                                    DatePicker::make('expiration_date')
                                        ->label('Expiration Date')
                                        ->native(false),
                                ]),
                            ]),

                        Tabs\Tab::make('Audit')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('reason_code')
                                        ->label('Reason Code')
                                        ->maxLength(20),

                                    TextInput::make('source_code')
                                        ->label('Source Code')
                                        ->maxLength(20),

                                    Textarea::make('comment')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('line_no')
            ->columns([
                TextColumn::make('line_no')
                    ->label('Line')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('entry_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'movement' => 'info',
                        'pick' => 'warning',
                        'put_away' => 'success',
                        'positive_adj' => 'success',
                        'negative_adj' => 'danger',
                        'physical_inventory' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pick' => 'Pick',
                        'put_away' => 'Put-Away',
                        'movement' => 'Movement',
                        'positive_adj' => '+Adj',
                        'negative_adj' => '−Adj',
                        'physical_inventory' => 'Phys. Inv.',
                        default => $state,
                    }),

                TextColumn::make('item.description')
                    ->label('Item')
                    ->limit(25)
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right'),

                TextColumn::make('unit_of_measure_code')
                    ->label('UOM'),

                TextColumn::make('sourceLocation.name')
                    ->label('From Location')
                    ->limit(20)
                    ->toggleable(),

                TextColumn::make('sourceBin.code')
                    ->label('From Bin')
                    ->toggleable(),

                TextColumn::make('destinationLocation.name')
                    ->label('To Location')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('destinationBin.code')
                    ->label('To Bin')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('posting_date')
                    ->label('Posting Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('line_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ((string) ($state?->value ?? $state)) {
                        'open' => 'info',
                        'checked' => 'warning',
                        'posted' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('entry_type')
                    ->options([
                        'pick' => 'Pick',
                        'put_away' => 'Put-Away',
                        'movement' => 'Movement',
                        'positive_adj' => 'Positive Adjustment',
                        'negative_adj' => 'Negative Adjustment',
                        'physical_inventory' => 'Physical Inventory',
                    ])
                    ->native(false),

                SelectFilter::make('line_status')
                    ->options(['open' => 'Open', 'checked' => 'Checked', 'posted' => 'Posted', 'rejected' => 'Rejected'])
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Line')
                    ->mutateFormDataUsing(function (array $data) {
                        if (empty($data['line_no'])) {
                            $max = $this->getOwnerRecord()->lines()->max('line_no') ?? 0;
                            $data['line_no'] = $max + 10000;
                        }

                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn ($record) => in_array($record->line_status?->value ?? $record->line_status, ['posted'])),
                DeleteAction::make()
                    ->hidden(fn ($record) => in_array($record->line_status?->value ?? $record->line_status, ['posted'])),
            ])
            ->defaultSort('line_no');
    }
}
