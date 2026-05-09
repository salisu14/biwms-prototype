<?php

namespace App\Filament\Resources\PhysicalInventoryJournals\RelationManagers;

use App\Filament\Resources\PhysicalInventoryJournals\PhysicalInventoryJournalResource;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\PhysicalInventoryLine;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = PhysicalInventoryJournalResource::class;

    protected static ?string $title = 'Phys. Inventory Lines';

    protected static ?string $recordTitleAttribute = 'line_no';

    public function form(Schema $schema): Schema
    {
        $journal = $this->getOwnerRecord();
        $isPosted = $journal?->status === 'Posted';
        $isCounting = $journal?->status === 'Counting';
        $isCalculated = $journal?->status === 'Calculated';
        $isOpen = $journal?->status === 'Open';

        /**
         * Shared calculation logic to update variance and amounts
         */
        $recalculateLine = function (Get $get, Set $set): void {
            $calculation = PhysicalInventoryLine::calculateCountVariance(
                systemQuantity: (float) ($get('quantity_base') ?: 0),
                physicalQuantity: (float) ($get('qty_physical_inventory') ?: 0),
                unitAmount: (float) ($get('unit_amount') ?: 0),
            );

            foreach ($calculation as $key => $value) {
                $set($key, $value);
            }
        };
        $resolveSystemQuantity = function (Get $get): float {
            $itemId = $get('item_id');
            if (empty($itemId)) {
                return 0.0;
            }

            $locationCode = $get('location_code');
            $binCode = $get('bin_code');
            $locationId = null;

            if (! empty($locationCode)) {
                $locationId = Location::query()
                    ->where('code', $locationCode)
                    ->value('id');
            }

            $ledgerQuery = ItemLedgerEntry::query()
                ->where('item_id', $itemId);

            if (! empty($locationId)) {
                $ledgerQuery->where('location_id', $locationId);
            }

            if (! empty($binCode)) {
                $ledgerQuery->where('bin_code', $binCode);
            }

            $ledgerQuantity = (float) ($ledgerQuery->sum('quantity') ?? 0);

            if ($ledgerQuantity !== 0.0 || ! empty($locationId) || ! empty($binCode)) {
                return $ledgerQuantity;
            }

            return (float) (Item::query()->whereKey($itemId)->value('inventory') ?? 0);
        };

        return $schema
            ->schema([
                Section::make('Item Information')
                    ->columns(3)
                    ->schema([
                        Select::make('item_id')
                            ->label('Item No.')
                            ->relationship('item', 'item_code')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled($isPosted || $isCalculated)
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) use ($recalculateLine) {
                                $item = Item::with(['baseUom', 'inventoryPostingGroup', 'generalProductPostingGroup'])->find($state);
                                if ($item) {
                                    // Auto-populate Item Master Data
                                    $set('item_description', $item->description);
                                    $set('unit_of_measure_code', $item->baseUom?->uom_code);
                                    $set('inventory_posting_group', $item->inventoryPostingGroup?->code);
                                    $set('gen_prod_posting_group', $item->generalProductPostingGroup?->code);
                                    $set('unit_amount', (float) ($item->unit_cost ?? 0));
                                    $set('shelf_no', $item->shelf_no);
                                    $set('quantity_base', $resolveSystemQuantity($get));

                                    // Set tracking flags
                                    $set('use_item_tracking', ! empty($item->item_tracking_code));

                                    // Trigger variance calculation
                                    $recalculateLine($get, $set);
                                }
                            }),

                        TextInput::make('line_no')
                            ->required()
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-generated by sequence (10000).'),

                        TextInput::make('item_description')
                            ->label('Description')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull(),
                    ]),

                Section::make('Quantities & Variance')
                    ->columns(3)
                    ->schema([
                        TextInput::make('quantity_base')
                            ->label('Qty. Expected (System)')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->prefixIcon('heroicon-m-computer-desktop')
                            ->helperText('Quantity currently in ledger.'),

                        TextInput::make('qty_physical_inventory')
                            ->label('Qty. Counted')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->disabled(! $isCounting && ! $isOpen)
                            ->live()
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->afterStateUpdated(fn (Get $get, Set $set) => $recalculateLine($get, $set))
                            ->helperText('Actual stock found on shelf.'),

                        TextInput::make('qty_calculated')
                            ->label('Difference')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Variance between System and Count.')
                            ->suffixAction(
                                Action::make('zero_difference')
                                    ->label('Match System')
                                    ->icon('heroicon-o-check-circle')
                                    ->color('success')
                                    ->visible($isCounting || $isOpen)
                                    ->action(function (Set $set, Get $get) use ($recalculateLine) {
                                        $set('qty_physical_inventory', $get('quantity_base'));
                                        $recalculateLine($get, $set);
                                    })
                            ),
                    ]),

                Section::make('Financial Impact')
                    ->columns(3)
                    ->visible(! $isOpen)
                    ->schema([
                        Select::make('entry_type')
                            ->options([
                                'Positive Adjmt.' => 'Positive Adjmt.',
                                'Negative Adjmt.' => 'Negative Adjmt.',
                            ])
                            ->disabled()
                            ->dehydrated()
                            ->placeholder('No variance'),

                        TextInput::make('unit_amount')
                            ->label('Unit Cost')
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('amount')
                            ->label('Adjustment Value')
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Section::make('Location & Tracking')
                    ->columns(3)
                    ->schema([
                        Select::make('location_code')
                            ->relationship('location', 'code')
                            ->default(fn () => $this->getOwnerRecord()?->location_code)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) use ($resolveSystemQuantity, $recalculateLine) {
                                $set('quantity_base', $resolveSystemQuantity($get));
                                $recalculateLine($get, $set);
                            })
                            ->disabled($isPosted),

                        Select::make('bin_code')
                            ->relationship('bin', 'bin_code')
                            ->default(fn () => $this->getOwnerRecord()?->bin_code)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) use ($resolveSystemQuantity, $recalculateLine) {
                                $set('quantity_base', $resolveSystemQuantity($get));
                                $recalculateLine($get, $set);
                            })
                            ->disabled($isPosted),

                        TextInput::make('shelf_no')
                            ->disabled($isPosted),

                        TextInput::make('serial_no')
                            ->label('Serial No.')
                            ->visible(fn (Get $get) => $get('use_item_tracking'))
                            ->required(fn (Get $get) => $get('use_item_tracking'))
                            ->disabled($isPosted),

                        TextInput::make('lot_no')
                            ->label('Lot No.')
                            ->visible(fn (Get $get) => $get('use_item_tracking'))
                            ->required(fn (Get $get) => $get('use_item_tracking'))
                            ->disabled($isPosted),

                        DatePicker::make('expiration_date')
                            ->visible(fn (Get $get) => $get('use_item_tracking'))
                            ->disabled($isPosted),
                    ]),

                Hidden::make('use_item_tracking'),
            ]);
    }

    public function table(Table $table): Table
    {
        $journal = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('line_no')
            ->columns([
                TextColumn::make('line_no')->sortable()->label('No.'),
                TextColumn::make('item.item_code')->label('Item No.')->searchable(),
                TextColumn::make('item.description')->label('Description')->limit(30),
                TextColumn::make('bin_code')->label('Bin'),

                TextColumn::make('quantity_base')
                    ->label('System Qty')
                    ->numeric(2)
                    ->color('gray'),

                TextColumn::make('qty_physical_inventory')
                    ->label('Counted')
                    ->numeric(2)
                    ->color(fn ($record) => $record->qty_physical_inventory != $record->quantity_base ? 'warning' : null)
                    ->weight('bold'),

                TextColumn::make('qty_calculated')
                    ->label('Difference')
                    ->numeric(2)
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('entry_type')
                    ->label('Adjustment')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('amount')
                    ->money('NGN')
                    ->summarize([Sum::make()->money('NGN')])
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('has_differences')
                    ->label('Show Discrepancies')
                    ->query(fn (Builder $query) => $query->where('qty_calculated', '!=', 0)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => $journal?->status === 'Open'),

                Action::make('suggest_lines')
                    ->label('Suggest Lines')
                    ->icon('heroicon-m-sparkles')
                    ->color('info')
                    ->visible(fn () => $journal?->status === 'Open')
                    ->schema([
                        Select::make('filter_type')
                            ->options([
                                'all' => 'All Items in Location',
                                'with_stock' => 'Items with Stock Only',
                            ])
                            ->default('with_stock'),
                    ])
                    ->action(fn () => /** Job execution logic would go here **/ null),
            ])
            ->recordActions([
                Action::make('enter_count')
                    ->label('Enter Count')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->visible(fn (Model $record) => $record->journal->status === 'Counting')
                    ->schema([
                        TextInput::make('qty_physical_inventory')
                            ->label('Actual Counted Qty')
                            ->required()
                            ->numeric()
                            ->autofocus(),
                    ])
                    ->action(function (Model $record, array $data) {
                        $calculation = PhysicalInventoryLine::calculateCountVariance(
                            systemQuantity: (float) $record->quantity_base,
                            physicalQuantity: (float) $data['qty_physical_inventory'],
                            unitAmount: (float) ($record->unit_amount ?? 0),
                        );

                        $record->update([
                            'qty_physical_inventory' => $data['qty_physical_inventory'],
                            ...$calculation,
                        ]);
                    }),
                EditAction::make()->visible(fn ($record) => $record->journal->status !== 'Posted'),
                DeleteAction::make()->visible(fn ($record) => $record->journal->status === 'Open'),
            ]);
    }
}
