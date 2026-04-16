<?php

namespace App\Filament\Resources\WarehousePutaways\RelationManagers;

use App\Filament\Resources\WarehousePutaways\WarehousePutawayResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = WarehousePutawayResource::class;

    protected static ?string $title = 'Put-away Actions (Take/Place)';
    protected static ?string $modelLabel = 'Action Line';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('line_no')
                        ->label('Line No.')
                        ->numeric()
                        ->required()
                        ->default(fn () => ($this->getOwnerRecord()->lines()->max('line_no') ?? 0) + 10),

                    Select::make('action_type')
                        ->options([
                            'Take' => 'Take (From Receipt Bin)',
                            'Place' => 'Place (In Storage Bin)',
                        ])
                        ->required()
                        ->native(false),

                    Select::make('item_id')
                        ->relationship('item', 'item_code')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_code} - {$record->description}")
                        ->searchable()
                        ->required(),
                ]),

                Section::make('Warehouse Location')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('bin_id')
                                ->label('Bin')
                                ->relationship(
                                    name: 'bin',
                                    titleAttribute: 'bin_code',
                                    modifyQueryUsing: fn (Builder $query) => $query->where('location_id', $this->getOwnerRecord()->location_id)
                                )
                                ->searchable()
                                ->required(),

                            TextInput::make('unit_of_measure')
                                ->label('UOM')
                                ->required()
                                ->default('PCS'),
                        ]),
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
                        ->required(),

                    TextInput::make('qty_handled')
                        ->label('Qty. Handled')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),
                ]),

                Section::make('Source Reference')
                    ->description('Traceability details required by the warehouse ledger.')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('source_document')
                                ->options([
                                    'Purchase Order' => 'Purchase Order',
                                    'Sales Return' => 'Sales Return',
                                    'Inbound Transfer' => 'Inbound Transfer',
                                    'Internal Put-away' => 'Internal Put-away',
                                ])
                                ->default('Internal Put-away')
                                ->required()
                                ->native(false),

                            TextInput::make('source_no')
                                ->label('Source Doc No.')
                                ->required()
                                ->default(fn () => $this->getOwnerRecord()->no),

                            TextInput::make('source_line_no')
                                ->label('Source Line No.')
                                ->numeric()
                                ->required()
                                ->default(0),
                        ]),
                        Toggle::make('breakbulk')
                            ->label('Breakbulk')
                            ->helperText('Enable if this line involves unit of measure conversion.')
                    ])->compact(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_no')->label('Line'),

                TextColumn::make('action_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Take' => 'warning',
                        'Place' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->description(fn ($record) => $record->item?->description),

                TextColumn::make('bin.bin_code')
                    ->label('Bin')
                    ->badge()
                    ->color('info'),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric(4)
                    ->alignment('right'),

                TextColumn::make('qty_to_handle')
                    ->label('To Handle')
                    ->numeric(4)
                    ->alignment('right'),

                TextColumn::make('source_no')
                    ->label('Source')
                    ->description(fn ($record) => $record->source_document)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('line_no')
            ->filters([
                SelectFilter::make('action_type')
                    ->options(['Take' => 'Take', 'Place' => 'Place']),
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
