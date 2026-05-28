<?php

namespace App\Filament\Resources\InventoryAdjustmentJournals\RelationManagers;

use App\Filament\Resources\InventoryAdjustmentJournals\InventoryAdjustmentJournalResource;
use App\Models\InventoryAdjustmentLine;
use App\Models\Item;
use App\Models\ReasonCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Journal Lines';

    protected static ?string $recordTitleAttribute = 'line_no';

    protected static ?string $relatedResource = InventoryAdjustmentJournalResource::class;

    public function form(Schema $schema): Schema
    {
        $journal = $this->getOwnerRecord();
        $isPosted = $journal?->status === 'Posted';
        $recalculateAmounts = function (Get $get, Set $set): void {
            $calculation = InventoryAdjustmentLine::calculateAmounts(
                quantity: (float) ($get('quantity') ?: 0),
                qtyPerUnitOfMeasure: (float) ($get('qty_per_unit_of_measure') ?: 1),
                unitCost: (float) ($get('unit_cost') ?: 0),
                lineDiscountAmount: (float) ($get('line_discount_amount') ?: 0),
            );

            foreach ($calculation as $key => $value) {
                $set($key, $value);
            }
        };

        return $schema
            ->schema([
                Section::make('Item Details')
                    ->columns(12)
                    ->schema([
                        TextInput::make('line_no')
                            ->required()
                            ->numeric()
                            ->default(fn () => (InventoryAdjustmentLine::where('journal_id', $this->getOwnerRecord()->id)->max('line_no') ?? 0) + 10000)
                            ->dehydrated()
                            ->disabled($isPosted)
                            ->columnSpan(2),

                        Select::make('item_id')
                            ->relationship('item', 'item_code')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled($isPosted)
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) use ($recalculateAmounts) {
                                $item = Item::find($state);
                                if ($item) {
                                    $set('description', $item->description);
                                    $set('unit_of_measure_code', $item->baseUom?->uom_code);
                                    $set('inventory_posting_group', $item->inventoryPostingGroup?->code);
                                    $set('gen_prod_posting_group', $item->generalProductPostingGroup?->code);
                                    $set('unit_cost', (float) ($item->unit_cost ?? 0));
                                    $set('bin_code', $item->bin_code);
                                    $set('line_discount_amount', 0);
                                    $recalculateAmounts($get, $set);
                                }
                            })
                            ->columnSpan(4),

                        TextInput::make('variant_code')
                            ->label('Variant Code')
                            ->placeholder('e.g., RED-XL')
                            ->disabled($isPosted)
                            ->columnSpan(2),

                        TextInput::make('description')
                            ->placeholder('Item description...')
                            ->disabled($isPosted)
                            ->columnSpan(4),
                    ]),

                Section::make('Quantities & Costs')
                    ->columns(3)
                    ->schema([
                        Select::make('entry_type')
                            ->options([
                                'Positive Adjmt.' => 'Positive Adjmt.',
                                'Negative Adjmt.' => 'Negative Adjmt.',
                            ])
                            ->required()
                            ->default('Positive Adjmt.')
                            ->disabled($isPosted),

                        TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->disabled($isPosted)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $recalculateAmounts($get, $set)),

                        Select::make('unit_of_measure_code')
                            ->relationship('unitOfMeasure', 'uom_code')
                            ->disabled($isPosted),

                        TextInput::make('qty_per_unit_of_measure')
                            ->numeric()
                            ->default(1)
                            ->disabled($isPosted)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $recalculateAmounts($get, $set)),

                        TextInput::make('unit_cost')
                            ->numeric()
                            ->prefix('₦')
                            ->disabled($isPosted)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $recalculateAmounts($get, $set)),

                        TextInput::make('line_discount_amount')
                            ->label('Discount Amount')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->disabled($isPosted)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $recalculateAmounts($get, $set)),

                        TextInput::make('line_amount')
                            ->label('Line Amount')
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Calculated after discount.'),

                        TextInput::make('quantity_base')
                            ->label('Qty. Base')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('quantity_to_handle')
                            ->label('Qty. to Handle')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('quantity_to_invoice')
                            ->label('Qty. to Invoice')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Section::make('Warehouse & Tracking')
                    ->columns(3)
                    ->schema([
                        Select::make('location_code')
                            ->relationship('location', 'code')
                            ->default(fn () => $this->getOwnerRecord()?->location_code)
                            ->disabled($isPosted),

                        Select::make('bin_code')
                            ->relationship('bin', 'bin_code')
                            ->disabled($isPosted),

                        Select::make('reason_code')
                            ->default(fn () => $this->getOwnerRecord()?->reason_code)
                            ->options(fn () => ReasonCode::query()
                                ->where('blocked', false)
                                ->orderBy('code')
                                ->pluck('description', 'code'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (! $state) {
                                    return;
                                }

                                $reason = ReasonCode::query()->where('code', $state)->first();
                                if (! $reason) {
                                    return;
                                }

                                if ($reason->default_location_code) {
                                    $set('location_code', $reason->default_location_code);
                                }

                                if ($reason->default_bin_code) {
                                    $set('bin_code', $reason->default_bin_code);
                                }
                            })
                            ->disabled($isPosted),

                        TextInput::make('serial_no')
                            ->disabled($isPosted)
                            ->visible(function (Get $get): bool {
                                $item = Item::find($get('item_id'));

                                return (bool) $item?->itemTrackingCodeDefinition?->requires_serial;
                            }),

                        TextInput::make('lot_no')
                            ->disabled($isPosted)
                            ->visible(function (Get $get): bool {
                                $item = Item::find($get('item_id'));
                                $trackingCode = $item?->itemTrackingCodeDefinition;

                                return (bool) ($trackingCode?->requires_lot || $trackingCode?->requires_serial);
                            }),

                        DatePicker::make('expiration_date')
                            ->disabled($isPosted),
                    ]),

                Section::make('Accounting & Dimensions')
                    ->columns(3)
                    ->schema([
                        TextInput::make('dimension_set_id')
                            ->numeric()
                            ->disabled($isPosted),

                        TextInput::make('shortcut_dimension_1_code')->label('Dimension 1')->disabled($isPosted),
                        TextInput::make('shortcut_dimension_2_code')->label('Dimension 2')->disabled($isPosted),
                        TextInput::make('applies_to_entry')->label('Applies-to Entry')->disabled($isPosted),

                        Select::make('inventory_posting_group')
                            ->relationship('inventoryPostingGroup', 'code')
                            ->disabled($isPosted),

                        Select::make('gen_prod_posting_group')
                            ->relationship('generalProductPostingGroup', 'code')
                            ->disabled($isPosted),

                        Select::make('gen_bus_posting_group')
                            ->relationship('generalBusinessPostingGroup', 'code')
                            ->disabled($isPosted),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('line_no')
            ->columns([
                TextColumn::make('line_no')
                    ->sortable(),

                TextColumn::make('item.item_code')
                    ->label('Item No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('item.description')
                    ->label('Description')
                    ->searchable(),

                TextColumn::make('entry_type')
                    ->badge()
                    ->colors([
                        'success' => 'Positive Adjmt.',
                        'danger' => 'Negative Adjmt.',
                    ]),

                TextColumn::make('quantity')
                    ->numeric(4),

                TextColumn::make('unit_of_measure_code'),

                TextColumn::make('unit_cost')
                    ->money('NGN')
                    ->toggleable(),

                TextColumn::make('amount')
                    ->money('NGN')
                    ->summarize([
                        Sum::make()
                            ->money('NGN'),
                    ]),

                TextColumn::make('location_code')
                    ->toggleable(),

                TextColumn::make('lot_no')
                    ->toggleable(),

                TextColumn::make('serial_no')
                    ->toggleable(),

                TextColumn::make('quantity_to_handle')
                    ->numeric(4)
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('qty_handled')
                    ->numeric(4)
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('entry_type')
                    ->options([
                        'Positive Adjmt.' => 'Positive Adjmt.',
                        'Negative Adjmt.' => 'Negative Adjmt.',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status !== 'Posted'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Model $record) => $record->journal->status !== 'Posted'),
                DeleteAction::make()
                    ->visible(fn (Model $record) => $record->journal->status !== 'Posted'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => $this->getOwnerRecord()->status !== 'Posted'),
                ]),
            ]);
    }
}
