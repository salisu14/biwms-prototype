<?php

namespace App\Filament\Resources\SalesInvoices\Schemas;

use App\Enums\ApprovalStatus;
use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Services\Sales\SalesPricingResolver;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SalesInvoiceForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Header Information')
                    ->columns(3)
                    ->schema([
                        static::makeSystemGeneratedTextInput(
                            'invoice_number',
                            'Invoice Number',
                            'Generated automatically from the sales invoice number series and cannot be changed.'
                        )->maxLength(255),

                        Select::make('customer_id')
                            ->relationship(
                                name: 'customer',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereNotNull('name')
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?SalesInvoice $record) => $record?->isPosted()),

                        Select::make('sales_order_id')
                            ->label('Sales Order')
                            ->relationship('salesOrder', 'order_number')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if (! $state) {
                                    return;
                                }

                                /** @var SalesOrder|null $salesOrder */
                                $salesOrder = SalesOrder::query()
                                    ->with('lines')
                                    ->find($state);

                                if (! $salesOrder) {
                                    return;
                                }

                                $lines = self::buildLinesFromSalesOrder($salesOrder);
                                $set('customer_id', $salesOrder->customer_id);
                                $set('currency_code', $salesOrder->currency_code ?: 'NGN');
                                $set('lines', $lines);
                                $set('total_amount', number_format(collect($lines)->sum(fn (array $line): float => (float) ($line['line_total'] ?? 0)), 2, '.', ''));
                            }),

                        Select::make('status')
                            ->options(ApprovalStatus::class)
                            ->required()
                            ->native(false)
                            ->default(ApprovalStatus::DRAFT)
                            ->disabled(fn (?SalesInvoice $record) => $record?->isPosted()),

                        DatePicker::make('invoice_date')
                            ->default(now())
                            ->required(),

                        DatePicker::make('due_date')
                            ->required(),

                        Select::make('currency_code')
                            ->options([
                                'NGN' => 'NGN - Naira',
                                'CYN' => 'CYN - Yuan',
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                            ])
                            ->default('USD')
                            ->required(),
                    ]),

                Section::make('Invoice Lines')
                    ->schema([
                        Repeater::make('lines')
                            ->relationship('lines') // <-- important! Must match hasMany in SalesInvoice model
                            ->dehydrated()
                            ->schema([
                                Select::make('item_id')
                                    ->label('Item')
                                    ->relationship('item', 'item_code', fn ($query) => $query->finishedGoods()->where('blocked', false))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if (! $state) {
                                            return;
                                        }

                                        $item = Item::find($state);
                                        if ($item) {
                                            $defaultSalesUom = $item->uoms()
                                                ->wherePivot('uom_type', 'SALES')
                                                ->wherePivot('is_default', true)
                                                ->first();
                                            $defaultUomCode = $defaultSalesUom?->uom_code ?? $item->base_unit_of_measure;
                                            $customer = Customer::find((int) $get('../../customer_id'));
                                            $pricing = app(SalesPricingResolver::class)->resolve(
                                                item: $item,
                                                customer: $customer,
                                                quantity: (float) ($get('quantity') ?? 1),
                                                variantCode: null,
                                                uom: $defaultUomCode
                                            );
                                            $set('description', $item->description);
                                            $set('unit_price', $pricing['unit_price']);
                                            $set('unit_of_measure', $defaultUomCode);
                                            $set('discount_percent', $pricing['discount_percent']);
                                            $set('discount_amount', $pricing['discount_amount']);
                                            SalesInvoiceForm::updateLineTotal($set, $get);
                                        }
                                    })
                                    ->columnSpan(2),

                                TextInput::make('description')
                                    ->required()
                                    ->columnSpan(3),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => SalesInvoiceForm::updateLineTotal($set, $get)),

                                Select::make('unit_of_measure')
                                    ->label('UOM')
                                    ->options(function (Get $get): array {
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
                                            ->mapWithKeys(fn ($uom) => [
                                                $uom->uom_code => $uom->uom_code,
                                            ])
                                            ->toArray();

                                        if (! array_key_exists($item->base_unit_of_measure, $uoms)) {
                                            $uoms[$item->base_unit_of_measure] = $item->base_unit_of_measure;
                                        }

                                        return $uoms;
                                    })
                                    ->required()
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        $item = Item::find((int) $get('item_id'));
                                        if (! $item || ! $state) {
                                            return;
                                        }

                                        $customer = Customer::find((int) $get('../../customer_id'));
                                        $pricing = app(SalesPricingResolver::class)->resolve(
                                            item: $item,
                                            customer: $customer,
                                            quantity: (float) ($get('quantity') ?? 1),
                                            variantCode: null,
                                            uom: $state
                                        );

                                        $set('unit_price', $pricing['unit_price']);
                                        $set('discount_percent', $pricing['discount_percent']);
                                        $set('discount_amount', $pricing['discount_amount']);
                                        SalesInvoiceForm::updateLineTotal($set, $get);
                                    }),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => SalesInvoiceForm::updateLineTotal($set, $get)),

                                TextInput::make('discount_percent')
                                    ->label('Disc. %')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => SalesInvoiceForm::updateLineTotal($set, $get)),

                                TextInput::make('discount_amount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->readOnly()
                                    ->dehydrated(),

                                TextInput::make('vat_percent')
                                    ->label('VAT %')
                                    ->numeric()
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, Get $get) => SalesInvoiceForm::updateLineTotal($set, $get)),

                                TextInput::make('line_total')
                                    ->numeric()
                                    ->readonly()
                                    ->dehydrated()
                                    ->prefix('$'),
                            ])
                            ->columns(5)
                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? 'New Line')
                            ->reorderableWithButtons()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => SalesInvoiceForm::updateGrandTotal($set, $get)),
                    ]),

                Section::make('Summary')
                    ->columns(2)
                    ->schema([
                        TextInput::make('total_amount')
                            ->numeric()
                            ->readonly()
                            ->prefix('$')
                            ->extraInputAttributes(['class' => 'font-bold text-lg']),

                        Placeholder::make('post_info')
                            ->label('Posting Details')
                            ->hidden(fn (?SalesInvoice $record) => ! $record?->isPosted())
                            ->content(fn (SalesInvoice $record) => "Posted by {$record->posted_by} on {$record->posted_at?->format('M d, Y H:i')}"),
                    ]),
            ]);
    }

    /**
     * Calculates the total for a single line in the repeater including VAT logic.
     */
    public static function updateLineTotal(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $discountPercent = (float) ($get('discount_percent') ?? 0);
        $discountAmount = $quantity * $unitPrice * ($discountPercent / 100);
        $vatPercent = (float) ($get('vat_percent') ?? 0);

        $subtotal = $quantity * $unitPrice;
        $afterDiscount = max(0, $subtotal - $discountAmount);
        $vatAmount = $afterDiscount * ($vatPercent / 100);
        $total = $afterDiscount + $vatAmount;

        $set('discount_amount', number_format($discountAmount, 2, '.', ''));
        $set('line_total', number_format($total, 2, '.', ''));

        // Push update to the grand total field outside the repeater
        self::updateGrandTotal($set, $get, true);
    }

    /**
     * Calculates the grand total of the entire invoice.
     */
    public static function updateGrandTotal(Set $set, Get $get, bool $isFromInsideRepeater = false): void
    {
        $lines = $isFromInsideRepeater ? $get('../../lines') : $get('lines');

        $total = collect($lines ?? [])
            ->map(function ($line) {
                return (float) ($line['line_total'] ?? 0);
            })
            ->sum();

        $path = $isFromInsideRepeater ? '../../total_amount' : 'total_amount';
        $set($path, number_format($total, 2, '.', ''));
    }

    public static function buildLinesFromSalesOrder(SalesOrder $salesOrder): array
    {
        return $salesOrder->lines
            ->map(function ($line): array {
                $quantityToInvoice = max(
                    0,
                    ((float) $line->quantity_shipped > 0 ? (float) $line->quantity_shipped : (float) $line->quantity)
                    - (float) $line->quantity_invoiced
                );
                $lineNet = $quantityToInvoice * (float) $line->unit_price;
                $lineDiscountAmount = $lineNet * ((float) $line->line_discount_percent / 100);
                $lineAmount = $lineNet - $lineDiscountAmount;
                $lineVat = $lineAmount * ((float) $line->vat_percentage / 100);

                return [
                    'item_id' => $line->item_id,
                    'description' => $line->description,
                    'quantity' => $quantityToInvoice,
                    'unit_of_measure' => $line->unit_of_measure_code,
                    'unit_price' => (float) $line->unit_price,
                    'discount_percent' => (float) $line->line_discount_percent,
                    'discount_amount' => 0,
                    'vat_percent' => (float) $line->vat_percentage,
                    'line_total' => number_format($lineAmount + $lineVat, 2, '.', ''),
                ];
            })
            ->filter(fn (array $line): bool => (float) $line['quantity'] > 0)
            ->values()
            ->all();
    }
}
