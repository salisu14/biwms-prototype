<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesCreditMemos\RelationManagers;

use App\Enums\SalesLinePricingStatus;
use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use App\Models\Item;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;
use App\Services\Sales\SalesPricingResolver;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $relatedResource = SalesCreditMemoResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('item_id')
                    ->relationship('item', 'item_code')
                    ->getOptionLabelFromRecordUsing(fn (Item $record): string => self::formatItemOption($record))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                        if (! $state) {
                            return;
                        }

                        $item = Item::find($state);
                        if (! $item) {
                            return;
                        }

                        /** @var SalesCreditMemo $memo */
                        $memo = $this->getOwnerRecord();

                        $defaultSalesUom = $item->uoms()
                            ->wherePivot('uom_type', 'SALES')
                            ->wherePivot('is_default', true)
                            ->first();
                        $defaultUomCode = $defaultSalesUom?->uom_code ?? $item->base_unit_of_measure;

                        // Commercial price comes from the canonical explicit-currency
                        // resolver, never from the item-card reference value.
                        $pricing = app(SalesPricingResolver::class)->resolveOrUnresolved(
                            item: $item,
                            customer: $memo->customer,
                            quantity: (float) ($get('quantity') ?? 1),
                            variantCode: null,
                            uom: $defaultUomCode,
                            documentCurrency: $memo->currency_code,
                        );

                        $set('unit_of_measure_code', $defaultUomCode);
                        self::applyResolvedPricing($set, $pricing);
                        self::calculateTotals($get, $set);
                    })
                    ->columnSpan(2),

                TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateTotals($get, $set)),

                TextInput::make('unit_price')
                    ->numeric()
                    ->prefix(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set): void {
                        // A user-entered price is an explicit manual commercial price;
                        // automatic provenance is cleared so it is never misattributed.
                        $set('pricing_status', SalesLinePricingStatus::MANUAL->value);
                        $set('price_source', null);
                        $set('pricing_master_id', null);
                        $set('price_record_id', null);

                        self::calculateTotals($get, $set);
                    }),

                Grid::make(3)
                    ->schema([
                        TextInput::make('line_discount_percent')
                            ->label('Disc %')
                            ->numeric()
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateTotals($get, $set)),

                        TextInput::make('vat_percent')
                            ->label('VAT %')
                            ->numeric()
                            ->default(7.5)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateTotals($get, $set)),

                        TextInput::make('unit_of_measure_code')
                            ->label('UOM')
                            ->placeholder('PCS'),
                    ]),

                Section::make('Totals')
                    ->columns(3)
                    ->compact()
                    ->schema([
                        Placeholder::make('amount_placeholder')
                            ->label('Net Amount')
                            ->content(fn (Get $get) => number_format((float) $get('amount'), 2)),

                        Placeholder::make('vat_amount_placeholder')
                            ->label('VAT Amount')
                            ->content(fn (Get $get) => number_format((float) $get('vat_amount'), 2)),

                        Placeholder::make('total_placeholder')
                            ->label('Total (Gross)')
                            ->extraAttributes(['class' => 'font-bold text-primary-600'])
                            ->content(fn (Get $get) => number_format((float) $get('amount_including_vat'), 2)),
                    ]),

                // Hidden fields to store calculated data
                Hidden::make('amount'),
                Hidden::make('vat_amount'),
                Hidden::make('amount_including_vat'),
                Hidden::make('line_discount_amount'),

                // Durable pricing provenance/state
                Hidden::make('price_source')->dehydrated(),
                Hidden::make('pricing_master_id')->dehydrated(),
                Hidden::make('price_record_id')->dehydrated(),
                Hidden::make('pricing_status')->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('line_no')
            ->columns([
                TextColumn::make('item_identity')
                    ->label('Item')
                    ->state(fn (SalesCreditMemoLine $record): string => self::formatItemIdentity($record))
                    ->description(fn ($record) => "UOM: {$record->unit_of_measure_code}"),

                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('unit_price')
                    ->money(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN')),

                TextColumn::make('line_discount_amount')
                    ->label('Discount')
                    ->money(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('amount')
                    ->label('Net')
                    ->money(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN')),

                TextColumn::make('vat_amount')
                    ->label('VAT')
                    ->money(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN')),

                TextColumn::make('amount_including_vat')
                    ->label('Gross')
                    ->money(fn (): string => (string) ($this->getOwnerRecord()->currency_code ?: 'NGN'))
                    ->weight('bold'),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->isPosted())
                    ->mutateDataUsing(function (array $data): array {
                        $data['line_no'] = self::getNextLineNo();

                        // Guarantee the canonical resolver prices the line even when
                        // this create is driven programmatically and the interactive
                        // item hook did not run. A positive price is never overwritten;
                        // a zero price always needs a trusted resolution.
                        /** @var SalesCreditMemo $memo */
                        $memo = $this->getOwnerRecord();
                        $item = Item::query()->find($data['item_id'] ?? null);
                        $hasPositivePrice = (float) ($data['unit_price'] ?? 0) > 0;

                        if ($item && ! $hasPositivePrice) {
                            $pricing = app(SalesPricingResolver::class)->resolveOrUnresolved(
                                item: $item,
                                customer: $memo->customer,
                                quantity: (float) ($data['quantity'] ?? 1),
                                variantCode: null,
                                uom: $data['unit_of_measure_code'] ?? $item->base_unit_of_measure,
                                documentCurrency: $memo->currency_code,
                            );

                            $data['unit_price'] = $pricing['unit_price'];
                            $data['price_source'] = $pricing['price_source'];
                            $data['pricing_master_id'] = $pricing['pricing_master_id'];
                            $data['price_record_id'] = $pricing['price_record_id'];
                            $data['pricing_status'] = $pricing['pricing_status'];
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->isPosted()),
                DeleteAction::make()
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->isPosted()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => ! $this->getOwnerRecord()->isPosted()),
                ]),
            ]);
    }

    public static function formatItemIdentity(SalesCreditMemoLine $line): string
    {
        $item = $line->item;

        if (! $item instanceof Item) {
            return '—';
        }

        return self::formatItemOption($item);
    }

    private static function formatItemOption(Item $item): string
    {
        return trim(implode(' - ', array_filter([
            $item->item_code,
            $item->description,
        ], fn (?string $value): bool => filled($value)))) ?: '—';
    }

    /**
     * Persist the canonical resolver's price and provenance onto the line state.
     *
     * @param  array<string, mixed>  $pricing
     */
    protected static function applyResolvedPricing(Set $set, array $pricing): void
    {
        $set('unit_price', $pricing['unit_price']);
        $set('price_source', $pricing['price_source']);
        $set('pricing_master_id', $pricing['pricing_master_id']);
        $set('price_record_id', $pricing['price_record_id']);
        $set('pricing_status', $pricing['pricing_status']);
    }

    /**
     * Logic to calculate line totals based on UI input
     */
    public static function calculateTotals(Get $get, Set $set): void
    {
        $qty = (float) $get('quantity') ?? 0;
        $price = (float) $get('unit_price') ?? 0;
        $discPercent = (float) $get('line_discount_percent') ?? 0;
        $vatPercent = (float) $get('vat_percent') ?? 0;

        $baseAmount = $qty * $price;
        $discountAmount = round($baseAmount * ($discPercent / 100), 2);
        $netAmount = $baseAmount - $discountAmount;
        $vatAmount = round($netAmount * ($vatPercent / 100), 2);
        $total = $netAmount + $vatAmount;

        $set('line_discount_amount', $discountAmount);
        $set('amount', $netAmount);
        $set('vat_amount', $vatAmount);
        $set('amount_including_vat', $total);
    }

    protected static function getNextLineNo(): int
    {
        // Simple logic to increment line numbers within the current memo
        return 10000; // You can implement specific logic here if needed
    }
}
