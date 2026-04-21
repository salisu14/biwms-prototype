<?php

namespace App\Filament\Resources\ItemJournalBatches\RelationManagers;

use App\Filament\Resources\ItemJournalBatches\ItemJournalBatchResource;
use App\Models\Item;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = ItemJournalBatchResource::class;

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('line_no')
                        ->label('Line No.')
                        ->numeric()
                        ->required()
                        ->default(fn ($livewire) => ($livewire->ownerRecord->lines()->max('line_no') ?? 0) + 10),

                    DatePicker::make('posting_date')
                        ->label('Posting Date')
                        ->required()
                        ->default(now())
                        ->native(false),

                    TextInput::make('document_no')
                        ->label('Document No.')
                        ->required()
                        ->maxLength(50),
                ]),

                Grid::make(2)->schema([
                    Select::make('item_id')
                        ->label('Item')
                        ->relationship('item', 'item_code')
                        ->getOptionLabelFromRecordUsing(fn (Item $record) => "{$record->item_code} – {$record->description}")
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull()
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set) {
                            if (!$state) return;
                            $item = Item::find($state);
                            if ($item) {
                                $set('description', $item->description);
                                $set('unit_cost', $item->unit_cost);
                                $set('unit_of_measure_code', $item->uom?->uom_code);
                            }
                        }),

                    TextInput::make('description')
                        ->required()
                        ->maxLength(255),

                    Select::make('location_id')
                        ->relationship('location', 'name')
                        ->default(fn ($livewire) => $livewire->ownerRecord->location_id)
                        ->required(),
                ]),

                Grid::make(3)->schema([
                    TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->step(0.0001)
                        ->default(1),

                    TextInput::make('unit_cost')
                        ->label('Unit Cost')
                        ->numeric()
                        ->prefix('$')
                        ->step(0.0001),

                    TextInput::make('amount')
                        ->label('Line Total')
                        ->numeric()
                        ->prefix('$')
                        ->readOnly()
                        ->helperText('Calculated from Qty * Cost.'),
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
                    ->label('Item Code')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 4)
                    ->alignment('right'),

                TextColumn::make('unit_cost')
                    ->money()
                    ->alignment('right')
                    ->toggleable(),

                TextColumn::make('amount')
                    ->money()
                    ->alignment('right')
                    ->weight('bold'),

                TextColumn::make('location.code')
                    ->label('Loc')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Line'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('line_no', 'asc');
    }
}
