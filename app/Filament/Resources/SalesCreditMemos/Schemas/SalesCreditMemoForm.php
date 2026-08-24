<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesCreditMemos\Schemas;

use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Location;
use App\Models\PostedSalesCreditMemoLine;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Services\Sales\SalesPricingResolver;
use Filament\Forms\Components\DatePicker;
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
            ->disabled(fn ($record) => $record?->isPosted())
            ->afterStateUpdated(function (Set $set): void {
                $set('posted_sales_invoice_id', null);
                $set('items', []);
            });
    }

    private static function makeInvoiceSelect(): Select
    {
        return Select::make('posted_sales_invoice_id')
            ->label('Link to Invoice')
            ->options(fn (Get $get): array => self::eligiblePostedInvoiceOptions((int) ($get('customer_id') ?? 0)))
            ->searchable()
            ->preload()
            ->placeholder('Optional: Select original invoice')
            ->helperText('Paid invoices remain eligible when returnable invoice quantities are still available.')
            ->disabled(fn ($record) => $record?->isPosted())
            ->live()
            ->afterStateUpdated(function ($state, Set $set): void {
                $set('sales_invoice_id', null);

                if (! $state) {
                    $set('items', []);

                    return;
                }

                $invoice = PostedSalesInvoice::query()
                    ->with('lines')
                    ->find($state);

                if ($invoice?->customer_id) {
                    $set('customer_id', $invoice->customer_id);
                }

                $set('items', self::postedInvoiceLineDefaults($invoice));
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
            ->dehydrated()
            ->saveRelationshipsUsing(null)
            ->live()
            ->schema(self::getRepeaterItemSchema())
            ->columns(12)
            ->reorderable(false)
            ->deleteAction(fn ($action) => $action->requiresConfirmation())
            ->itemLabel(fn (array $state): ?string => $state['description'] ?? null
            );
    }

    private static function getRepeaterItemSchema(): array
    {
        return [
            self::makePostedInvoiceLineSelect(),
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

    private static function makePostedInvoiceLineSelect(): Select
    {
        return Select::make('posted_sales_invoice_line_id')
            ->label('Invoice Line')
            ->options(fn (Get $get): array => self::eligiblePostedInvoiceLineOptions((int) ($get('../../posted_sales_invoice_id') ?? 0)))
            ->searchable()
            ->preload()
            ->live()
            ->dehydrated()
            ->visible(fn (Get $get): bool => filled($get('../../posted_sales_invoice_id')))
            ->required(fn (Get $get): bool => filled($get('../../posted_sales_invoice_id')))
            ->columnSpan(4)
            ->afterStateUpdated(function ($state, Set $set): void {
                if (! $state) {
                    return;
                }

                $postedLine = PostedSalesInvoiceLine::query()->find($state);

                if ($postedLine) {
                    self::populatePostedInvoiceLineFields($postedLine, $set);
                }
            });
    }

    private static function makeItemSelect(): Select
    {
        return Select::make('item_id')
            ->relationship('item', 'item_code', fn ($query) => $query->finishedGoods()->where('blocked', false)
            )
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->disabled(fn (Get $get): bool => filled($get('../../posted_sales_invoice_id')))
            ->dehydrated()
            ->columnSpan(fn (Get $get): int => filled($get('../../posted_sales_invoice_id')) ? 3 : 4)
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                if (! $state) {
                    return;
                }

                self::populateItemFields($state, $set, $get);
            });
    }

    private static function populateItemFields(string|int $itemId, Set $set, Get $get): void
    {
        $item = Item::find($itemId);
        if (! $item) {
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
            ->readOnly(fn (Get $get): bool => filled($get('posted_sales_invoice_line_id')))
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
            ->readOnly(fn (Get $get): bool => filled($get('posted_sales_invoice_line_id')))
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
            ->disabled(fn (Get $get): bool => filled($get('posted_sales_invoice_line_id')))
            ->dehydrated()
            ->columnSpan(2)
            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                if (! $state || ! $get('item_id')) {
                    return;
                }

                self::handleUomChange($state, $set, $get);
            });
    }

    private static function getUomOptions(Get $get): array
    {
        $itemId = $get('item_id');
        if (! $itemId) {
            return [];
        }

        $item = Item::find($itemId);
        if (! $item) {
            return [];
        }

        $uoms = $item->uoms()
            ->get()
            ->mapWithKeys(fn ($uom) => [$uom->uom_code => $uom->uom_code])
            ->toArray();

        // Ensure base UOM is always available
        if (! array_key_exists($item->base_unit_of_measure, $uoms)) {
            $uoms[$item->base_unit_of_measure] = $item->base_unit_of_measure;
        }

        return $uoms;
    }

    private static function handleUomChange(string $newUom, Set $set, Get $get): void
    {
        $item = Item::find($get('item_id'));
        if (! $item) {
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
        if (! $currentUom) {
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
                    ->state(fn ($record) => $record?->status?->getLabel() ?? 'Draft'
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

    /**
     * @return array<int, string>
     */
    private static function eligiblePostedInvoiceOptions(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        return PostedSalesInvoice::query()
            ->where('customer_id', $customerId)
            ->where('cancelled', false)
            ->whereHas('lines', fn ($query) => $query->whereNotNull('item_id'))
            ->orderByDesc('posting_date')
            ->limit(50)
            ->get()
            ->filter(fn (PostedSalesInvoice $invoice): bool => self::hasReturnablePostedInvoiceLine($invoice))
            ->mapWithKeys(fn (PostedSalesInvoice $invoice): array => [
                $invoice->id => sprintf(
                    '%s | %s | %s %s | %s',
                    $invoice->document_number,
                    optional($invoice->posting_date)->format('Y-m-d') ?? '-',
                    $invoice->currency_code ?? 'NGN',
                    number_format((float) $invoice->grand_total, 2),
                    $invoice->status,
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function eligiblePostedInvoiceLineOptions(int $postedInvoiceId): array
    {
        if ($postedInvoiceId <= 0) {
            return [];
        }

        return PostedSalesInvoiceLine::query()
            ->with('item')
            ->where('posted_sales_invoice_id', $postedInvoiceId)
            ->whereNotNull('item_id')
            ->orderBy('line_number')
            ->get()
            ->filter(fn (PostedSalesInvoiceLine $line): bool => self::remainingReturnableQuantity($line) > 0.000001)
            ->mapWithKeys(fn (PostedSalesInvoiceLine $line): array => [
                $line->id => sprintf(
                    '%s | %s | Invoiced %s %s | Remaining %s %s',
                    $line->item_code ?? $line->item?->item_code ?? ('#'.$line->item_id),
                    $line->item_description,
                    number_format(abs((float) $line->quantity), 4),
                    $line->unit_of_measure_code,
                    number_format(self::remainingReturnableQuantity($line), 4),
                    $line->unit_of_measure_code,
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function postedInvoiceLineDefaults(?PostedSalesInvoice $invoice): array
    {
        if (! $invoice) {
            return [];
        }

        return $invoice->lines
            ->filter(fn (PostedSalesInvoiceLine $line): bool => $line->item_id !== null && self::remainingReturnableQuantity($line) > 0.000001)
            ->map(function (PostedSalesInvoiceLine $line): array {
                $remainingQuantity = self::remainingReturnableQuantity($line);

                return [
                    'posted_sales_invoice_line_id' => $line->id,
                    'item_id' => $line->item_id,
                    'description' => $line->item_description,
                    'quantity' => $remainingQuantity,
                    'unit_price' => abs((float) $line->unit_price),
                    'vat_percent' => (float) $line->vat_percentage,
                    'line_discount_percent' => (float) $line->line_discount_percent,
                    'line_discount_amount' => abs((float) $line->line_discount_amount),
                    'unit_of_measure_code' => $line->unit_of_measure_code,
                    'qty_per_unit_of_measure' => (float) $line->qty_per_unit_of_measure,
                ];
            })
            ->values()
            ->all();
    }

    private static function populatePostedInvoiceLineFields(PostedSalesInvoiceLine $line, Set $set): void
    {
        $set('item_id', $line->item_id);
        $set('description', $line->item_description);
        $set('unit_price', abs((float) $line->unit_price));
        $set('vat_percent', (float) $line->vat_percentage);
        $set('line_discount_percent', (float) $line->line_discount_percent);
        $set('line_discount_amount', abs((float) $line->line_discount_amount));
        $set('unit_of_measure_code', $line->unit_of_measure_code);
        $set('qty_per_unit_of_measure', (float) $line->qty_per_unit_of_measure);
        $set('quantity', self::remainingReturnableQuantity($line));
    }

    private static function hasReturnablePostedInvoiceLine(PostedSalesInvoice $invoice): bool
    {
        $invoice->loadMissing('lines');

        return $invoice->lines->contains(
            fn (PostedSalesInvoiceLine $line): bool => $line->item_id !== null && self::remainingReturnableQuantity($line) > 0.000001
        );
    }

    private static function remainingReturnableQuantity(PostedSalesInvoiceLine $line): float
    {
        $alreadyCredited = PostedSalesCreditMemoLine::query()
            ->join('posted_sales_credit_memos as headers', 'headers.id', '=', 'posted_sales_credit_memo_lines.posted_sales_credit_memo_id')
            ->where('posted_sales_credit_memo_lines.corrected_invoice_line_id', $line->id)
            ->where('headers.corrected', false)
            ->sum('posted_sales_credit_memo_lines.quantity');

        return max(0.0, abs((float) $line->quantity) - abs((float) $alreadyCredited));
    }
}
