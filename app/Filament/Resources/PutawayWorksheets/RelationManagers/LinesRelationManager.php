<?php

namespace App\Filament\Resources\PutawayWorksheets\RelationManagers;

use App\Models\Bin;
use App\Models\Item;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $recordTitleAttribute = 'line_no';

    protected static ?string $pluralLabel = 'Worksheet lines';

    protected static ?string $pluralHint = 'Worksheet lines';

    protected static ?string $label = 'line';

    /**
     * FIX: Removed the relatedResource pointing to ExpenseTransactionResource.
     * This was forcing the table to look for "posting_date" for sorting.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->relationship('item', 'item_code')
                    ->getOptionLabelFromRecordUsing(fn (Item $record) => "{$record->item_code} - {$record->description}")
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),

                Select::make('warehouse_receipt_id')
                    ->relationship('warehouseReceipt', 'id')
                    ->label('Origin Receipt')
                    ->searchable()
                    ->required()
                    ->helperText('Select the specific receipt this line originates from.'),

                TextInput::make('source_no')
                    ->label('Source Document #')
                    ->placeholder('e.g. PO-12345'),

                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->suffix(fn ($record) => $record?->item?->uom?->uom_code ?? 'Units'),

                TextInput::make('qty_to_handle')
                    ->label('Qty. to Handle')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->live()
                    ->helperText('Quantity currently being moved to the bin.'),

                Select::make('bin_id')
                    ->label('Target Bin')
                    ->relationship('bin', 'bin_code')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $get, $set) {
                        $bin = Bin::find($state);
                        $item = Item::find($get('item_id'));
                        $qty = (float) $get('qty_to_handle');

                        if ($bin && $item) {
                            $validation = $bin->validateCapacity($item, $qty);
                            if ($validation['status'] === 'warning') {
                                // Filament doesn't have a native 'warning' state for fields,
                                // but we can use helper text or a notification.
                                Notification::make()
                                    ->title($validation['message'])
                                    ->warning()
                                    ->send();
                            }
                        }
                    })
                    ->rules([
                        fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $bin = Bin::find($value);
                            $item = Item::find($get('item_id'));
                            $qty = (float) $get('qty_to_handle');

                            if ($bin && $item) {
                                $validation = $bin->validateCapacity($item, $qty);
                                if ($validation['status'] === 'error') {
                                    $fail($validation['message']);
                                }
                            }
                        },
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('item.description')
                    ->label('Description')
                    ->limit(30),

                TextColumn::make('source_no')
                    ->label('Source Doc')
                    ->placeholder('-'),

                TextColumn::make('quantity')
                    ->label('Total Qty')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right'),

                TextColumn::make('qty_to_handle')
                    ->label('Qty to Handle')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right')
                    ->color('info'),

                TextColumn::make('bin.bin_code')
                    ->label('Target Bin')
                    ->badge()
                    ->color('success'),
            ])
            // FIX: Explicitly sort by ID to ensure it uses a valid column
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
