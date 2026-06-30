<?php

namespace App\Filament\Resources\SalesCreditMemos\Schemas;

use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Services\Sales\SalesPricingResolver;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SalesCreditMemoForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::makeMainColumn(),
                self::makeSidebarColumn(),
            ])
            ->columns(3);
    }

    private static function makeMainColumn(): Group
    {
        return Group::make()
            ->schema([
                self::makeGeneralInformationSection(),
                self::makeCreditItemsSection(),
            ])
            ->columnSpan(['lg' => 2]);
    }

    private static function makeSidebarColumn(): Group
    {
        return Group::make()
            ->schema([
                self::makeStatusAndDatesSection(),
                self::makeFinancialTotalsSection(),
            ])
            ->columnSpan(['lg' => 1]);
    }

    private static function makeGeneralInformationSection(): Section
    {
        return Section::make('General Information')
            ->schema([
                self::makeMemoNumberField(),
                self::makeCustomerSelect(),
                self::makeInvoiceSelect(),
            ])
            ->columns(2);
    }

    private static function makeMemoNumberField(): TextInput
    {
        return static::makeSystemGeneratedTextInput(
            'memo_number',
            'Memo Number',
            'Generated automatically from the sales credit memo number series and cannot be changed.'
        )->prefix('#');
    }

    private static function makeCustomerSelect(): Select
    {
        return Select::make('customer_id')
            ->relationship('customer', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->disabled(fn ($record) => $record?->isPosted());
    }

    private static function makeInvoiceSelect(): Select
    {
        return Select::make('sales_invoice_id')
            ->label('Link to Invoice')
            ->relationship('invoice', 'invoice_number')
            ->searchable()
            ->preload()
            ->placeholder('Optional: Select original invoice')
            ->disabled(fn ($record) => $record?->isPosted())
            ->live()
            ->afterStateUpdated(function ($state, Set $set) {
                if (!$state) {
                    return;
                }

                $invoice = SalesInvoice::find($state);
                if ($invoice?->customer_id) {
                    $set('customer_id', $invoice->customer_id);
                }
            });
    }

    private static function makeCreditItemsSection(): Section
    {
        return Section::make('Credit Items')
            ->description('List the items being credited')
            ->schema([
                self::makeItemsRepeater(),
            ]);
    }

    private static function makeItemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->relationship()
            ->live()
            ->schema(self::getRepeaterItemSchema())
            ->columns(12)
            ->reorderable(false)
            ->deleteAction(fn ($action) => $action->requiresConfirmation())
            ->itemLabel(fn (array $state): ?string =>
                $state['description'] ?? null
            );
    }

    private static function getRepeaterItemSchema(): array
    {
        return [
            self::makeItemSelect(),
            self::makeDescriptionField(),
            self::makeQuantityField(),
            self::makeUnitPriceField(),
            self::makeVatPercentField(),
            self::makeAmountIncludingVatField(),
            self::makeUomSelect(),
            self::makeQtyPerUomField(),
        ];
    }

    private static function makeItemSelect(): Select
    {
        return Select::make('item_id')
            ->relationship('item', 'item_code', fn ($query) =>
            $query->finishedGoods()->where('blocked', false)
            )
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->columnSpan(4)
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                if (!$state) {
                    return;
                }

                self::populateItemFields($state, $set, $get);
            });
    }

    private static function populateItemFields(string|int $itemId, Set $set, Get $get): void
    {
        $item = Item::find($itemId);
        if (!$item) {
            return;
        }

        $context = self::resolvePricingContext($get);
        $defaultUom = self::resolveDefaultSalesUom($item);

        $pricing = self::resolvePricing($item, $context, $defaultUom);
        $conversionFactor = $item->getConversionFactorForUom($defaultUom) ?? 1;

        $set('description', $item->description);
        $set('item_code', $item->item_code);
        $set('unit_price', $pricing['unit_price']);
        $set('line_discount_percent', $pricing['discount_percent']);
        $set('price_source', $pricing['price_source']);
        $set('pricing_master_id', $pricing['pricing_master_id']);
        $set('unit_of_measure_code', $defaultUom);
        $set('qty_per_unit_of_measure', $conversionFactor);
    }

    private static function resolveDefaultSalesUom(Item $item): string
    {
        $defaultSalesUom = $item->uoms()
            ->wherePivot('uom_type', 'SALES')
            ->wherePivot('is_default', true)
            ->first();

        return $defaultSalesUom?->uom_code ?? $item->base_unit_of_measure;
    }

    private static function resolvePricingContext(Get $get): array
    {
        return [
            'customer' => Customer::find((int) $get('../../customer_id')),
            'location' => Location::find((int) $get('../../location_id')),
            'quantity' => (float) ($get('quantity') ?? 1),
        ];
    }

    private static function resolvePricing(
        Item $item,
        array $context,
        string $uom
    ): array {
        return app(SalesPricingResolver::class)->resolve(
            item: $item,
            customer: $context['customer'],
            quantity: $context['quantity'],
            variantCode: null,
            uom: $uom,
            location: $context['location']
        );
    }

    private static function makeDescriptionField(): TextInput
    {
        return TextInput::make('description')
            ->label('Description')
            ->columnSpan(5)
            ->placeholder('Select an item to see description')
            ->readOnly();
    }

    private static function makeQuantityField(): TextInput
    {
        return TextInput::make('quantity')
            ->numeric()
            ->default(1)
            ->required()
            ->minValue(0.01)
            ->step(0.01)
            ->live(onBlur: true)
            ->columnSpan(2)
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                // Recalculate pricing when quantity changes
                $itemId = $get('item_id');
                if ($itemId) {
                    self::updatePricingForCurrentUom($set, $get);
                }
            });
    }

    private static function makeUnitPriceField(): TextInput
    {
        return TextInput::make('unit_price')
            ->label('Unit Price')
            ->numeric()
            ->required()
            ->minValue(0)
            ->step(0.01)
            ->live(onBlur: true)
            ->columnSpan(3);
    }

    private static function makeVatPercentField(): TextInput
    {
        return TextInput::make('vat_percent')
            ->label('VAT %')
            ->numeric()
            ->default(0)
            ->minValue(0)
            ->maxValue(100)
            ->step(0.1)
            ->live(onBlur: true)
            ->columnSpan(1);
    }

    private static function makeAmountIncludingVatField(): TextInput
    {
        return TextInput::make('amount_including_vat')
            ->label('Line Total (Gross)')
            ->numeric()
            ->readOnly()
            ->dehydrated(false)
            ->placeholder(fn (Get $get) => self::calculateLineTotal($get))
            ->columnSpan(2);
    }

    private static function calculateLineTotal(Get $get): string
    {
        $qty = (float) ($get('quantity') ?? 0);
        $price = (float) ($get('unit_price') ?? 0);
        $vatPercent = (float) ($get('vat_percent') ?? 0);

        $netTotal = $qty * $price;
        $vatAmount = $netTotal * ($vatPercent / 100);
        $grossTotal = $netTotal + $vatAmount;

        return number_format($grossTotal, 2);
    }

    private static function makeUomSelect(): Select
    {
        return Select::make('unit_of_measure_code')
            ->label('UOM')
            ->options(fn (Get $get) => self::getUomOptions($get))
            ->required()
            ->live()
            ->columnSpan(2)
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                if (!$state || !$get('item_id')) {
                    return;
                }

                self::handleUomChange($state, $set, $get);
            });
    }

    private static function getUomOptions(Get $get): array
    {
        $itemId = $get('item_id');
        if (!$itemId) {
            return [];
        }

        $item = Item::find($itemId);
        if (!$item) {
            return [];
        }

        $uoms = $item->uoms()
            ->get()
            ->mapWithKeys(fn ($uom) => [$uom->uom_code => $uom->uom_code])
            ->toArray();

        // Ensure base UOM is always available
        if (!array_key_exists($item->base_unit_of_measure, $uoms)) {
            $uoms[$item->base_unit_of_measure] = $item->base_unit_of_measure;
        }

        return $uoms;
    }

    private static function handleUomChange(string $newUom, Set $set, Get $get): void
    {
        $item = Item::find($get('item_id'));
        if (!$item) {
            return;
        }

        $context = self::resolvePricingContext($get);
        $conversionFactor = $item->getConversionFactorForUom($newUom) ?? 1;

        $newPricing = self::resolvePricing($item, $context, $newUom);

        $set('qty_per_unit_of_measure', $conversionFactor);
        $set('unit_price', $newPricing['unit_price']);
        $set('line_discount_percent', $newPricing['discount_percent']);
        $set('price_source', $newPricing['price_source']);
        $set('pricing_master_id', $newPricing['pricing_master_id']);
    }

    private static function updatePricingForCurrentUom(Set $set, Get $get): void
    {
        $currentUom = $get('unit_of_measure_code');
        if (!$currentUom) {
            return;
        }

        self::handleUomChange($currentUom, $set, $get);
    }

    private static function makeQtyPerUomField(): TextInput
    {
        return TextInput::make('qty_per_unit_of_measure')
            ->label('Qty/UOM')
            ->numeric()
            ->readOnly()
            ->dehydrated()
            ->columnSpan(2);
    }

    private static function makeStatusAndDatesSection(): Section
    {
        return Section::make('Status & Dates')
            ->schema([
                TextEntry::make('status')
                    ->state(fn ($record) =>
                        $record?->status?->getLabel() ?? 'Draft'
                    ),

                DatePicker::make('effective_date')
                    ->default(now())
                    ->required(),

                Textarea::make('reason')
                    ->placeholder('Reason for credit memo...')
                    ->rows(3),
            ]);
    }

    private static function makeFinancialTotalsSection(): Section
    {
        return Section::make('Financial Totals')
            ->schema([
                self::makeTotalAmountField(),
                self::makeCurrencySelect(),
            ]);
    }

    private static function makeTotalAmountField(): TextInput
    {
        return TextInput::make('total_amount')
            ->label('Total (Incl. VAT)')
            ->numeric()
            ->prefix('₦')
            ->readOnly()
            ->dehydrated(false)
            ->placeholder(fn (Get $get) => self::calculateGrandTotal($get));
    }

    private static function calculateGrandTotal(Get $get): string
    {
        $items = collect($get('items'));

        $grandTotal = $items->reduce(function (float $carry, array $item): float {
            $net = (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
            $vat = $net * ((float) ($item['vat_percent'] ?? 0) / 100);

            return $carry + $net + $vat;
        }, 0);

        return number_format($grandTotal, 2);
    }

    private static function makeCurrencySelect(): Select
    {
        return Select::make('currency_code')
            ->options([
                'NGN' => 'Naira',
                'USD' => 'USD',
                'EUR' => 'EUR',
            ])
            ->default('NGN');
    }
}
