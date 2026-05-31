<?php

namespace App\Filament\Resources\PurchaseCreditMemos\Schemas;

use App\Enums\ApprovalStatus;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\ReasonCode;
use App\Models\Vendor;
use App\Services\NumberSeriesService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PurchaseCreditMemoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('General Information')
                            ->schema([
                                TextInput::make('document_number')
                                    ->label('Document Number')
                                    ->placeholder('Auto-generated from Number Series')
                                    ->disabled()
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->helperText('Auto-generated from Number Series (P-CM, PURCHASE_CREDIT_MEMO, or PCM).')
                                    ->formatStateUsing(function (?string $state) {
                                        if (! empty($state)) {
                                            return $state;
                                        }

                                        $service = app(NumberSeriesService::class);

                                        foreach (['P-CM', 'PURCHASE_CREDIT_MEMO', 'PCM'] as $seriesCode) {
                                            $nextNo = $service->tryGetNextNo($seriesCode);
                                            if (! empty($nextNo)) {
                                                return $nextNo;
                                            }
                                        }

                                        return 'SETUP-NO-SERIES';
                                    }),

                                Select::make('vendor_id')
                                    ->relationship('vendor', 'vendor_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->disabled(fn (Get $get): bool => filled($get('corrects_invoice_id')))
                                    ->afterStateHydrated(function ($state, Set $set) {
                                        if ($state) {
                                            $vendor = Vendor::find($state);
                                            if ($vendor) {
                                                $set('vendor_name', $vendor->name);
                                            }
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $vendor = Vendor::find($state);
                                            if ($vendor) {
                                                $set('vendor_name', $vendor->name);
                                            }
                                        }
                                    }),

                                TextInput::make('vendor_name')
                                    ->required()
                                    ->disabled(),

                                Select::make('corrects_invoice_id')
                                    ->label('Corrects Posted Purchase Invoice')
                                    ->searchable()
                                    ->options(function (Get $get): array {
                                        $vendorId = $get('vendor_id');

                                        return PurchaseInvoice::query()
                                            ->whereNotNull('posted_at')
                                            ->where('cancelled', false)
                                            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId))
                                            ->orderByDesc('posting_date')
                                            ->orderByDesc('id')
                                            ->get()
                                            ->mapWithKeys(fn (PurchaseInvoice $invoice): array => [
                                                $invoice->id => sprintf(
                                                    '%s | %s | %s %.2f',
                                                    $invoice->document_number,
                                                    optional($invoice->posting_date)?->format('Y-m-d') ?? '-',
                                                    $invoice->currency_code ?? 'NGN',
                                                    (float) $invoice->grand_total
                                                ),
                                            ])
                                            ->all();
                                    })
                                    ->getSearchResultsUsing(function (string $search, Get $get): array {
                                        $vendorId = $get('vendor_id');

                                        return PurchaseInvoice::query()
                                            ->whereNotNull('posted_at')
                                            ->where('cancelled', false)
                                            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId))
                                            ->where(function ($query) use ($search): void {
                                                $query->where('document_number', 'like', "%{$search}%")
                                                    ->orWhere('vendor_name', 'like', "%{$search}%")
                                                    ->orWhere('order_number', 'like', "%{$search}%");
                                            })
                                            ->orderByDesc('posting_date')
                                            ->orderByDesc('id')
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn (PurchaseInvoice $invoice): array => [
                                                $invoice->id => sprintf(
                                                    '%s | %s | %s %.2f',
                                                    $invoice->document_number,
                                                    optional($invoice->posting_date)?->format('Y-m-d') ?? '-',
                                                    $invoice->currency_code ?? 'NGN',
                                                    (float) $invoice->grand_total
                                                ),
                                            ])
                                            ->all();
                                    })
                                    ->live()
                                    ->helperText('Select a posted purchase invoice. Its lines are loaded; remove or adjust quantities/costs for partial credits.')
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $invoice = PurchaseInvoice::query()
                                                ->with('lines')
                                                ->find($state);
                                            if ($invoice) {
                                                $set('vendor_id', $invoice->vendor_id);
                                                $set('vendor_name', $invoice->vendor_name);
                                                $set('corrects_invoice_number', $invoice->document_number);
                                                $set('currency_code', $invoice->currency_code);
                                                $set('location_id', $invoice->location_id);
                                                $set('lines', $invoice->lines
                                                    ->filter(fn ($line) => ! empty($line->item_id))
                                                    ->values()
                                                    ->map(fn ($line) => [
                                                        'is_selected' => true,
                                                        'item_id' => $line->item_id,
                                                        'item_code' => $line->item_code,
                                                        'quantity' => (float) $line->quantity,
                                                        'unit_cost' => (float) $line->unit_cost,
                                                        'tax_percent' => (float) $line->vat_percentage,
                                                        'max_credit_quantity' => (float) $line->quantity,
                                                        'general_product_posting_group_id' => $line->general_product_posting_group_id,
                                                        'unit_of_measure_code' => $line->unit_of_measure_code,
                                                    ])
                                                    ->all());
                                            }
                                        } else {
                                            $set('corrects_invoice_number', null);
                                            $set('lines', []);
                                        }
                                    }),

                                TextInput::make('corrects_invoice_number')
                                    ->hidden(),

                                Select::make('reason_code')
                                    ->label('Reason Code')
                                    ->options(fn () => ReasonCode::query()->orderBy('code')->pluck('description', 'code'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])->columns(2),

                        Section::make('Credit Items')
                            ->schema([
                                Repeater::make('lines')
                                    ->relationship()
                                    ->live()
                                    ->addable(fn (Get $get): bool => blank($get('corrects_invoice_id')))
                                    ->schema([
                                        Toggle::make('is_selected')
                                            ->label('Select')
                                            ->default(true)
                                            ->live()
                                            ->columnSpan(1),

                                        Select::make('item_id')
                                            ->relationship('item', 'description')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->disabled(fn (Get $get): bool => ! ((bool) $get('is_selected')) || filled($get('../../corrects_invoice_id')))
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if ($state) {
                                                    $correctsInvoiceId = $get('../../corrects_invoice_id');
                                                    if ($correctsInvoiceId) {
                                                        $invoiceLine = PurchaseInvoice::query()
                                                            ->with('lines')
                                                            ->find($correctsInvoiceId)
                                                            ?->lines
                                                            ->firstWhere('item_id', $state);

                                                        if ($invoiceLine) {
                                                            $set('unit_cost', (float) $invoiceLine->unit_cost);
                                                            $set('tax_percent', (float) $invoiceLine->vat_percentage);
                                                            $set('unit_of_measure_code', $invoiceLine->unit_of_measure_code ?: 'EA');
                                                            $set('item_code', $invoiceLine->item_code);
                                                            $set('general_product_posting_group_id', $invoiceLine->general_product_posting_group_id);

                                                            return;
                                                        }
                                                    }

                                                    $item = Item::find($state);
                                                    $set('unit_cost', $item?->unit_cost ?? 0);
                                                    $set('unit_of_measure_code', $item?->uom_code ?? 'EA');
                                                    $set('item_code', $item?->item_code);
                                                    $set('general_product_posting_group_id', $item?->general_product_posting_group_id);
                                                }
                                            })
                                            ->columnSpan(4),

                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->minValue(0.0001)
                                            ->maxValue(fn (Get $get): ?float => filled($get('max_credit_quantity')) ? (float) $get('max_credit_quantity') : null)
                                            ->live(onBlur: true)
                                            ->disabled(fn (Get $get): bool => ! ((bool) $get('is_selected')))
                                            ->helperText(function (Get $get): ?string {
                                                $maxQty = $get('max_credit_quantity');
                                                if (blank($maxQty)) {
                                                    return null;
                                                }

                                                return 'Max creditable qty for this line: '.number_format((float) $maxQty, 4);
                                            })
                                            ->columnSpan(2),

                                        TextInput::make('unit_cost')
                                            ->label('Unit Cost')
                                            ->numeric()
                                            ->required()
                                            ->live(onBlur: true)
                                            ->disabled(fn (Get $get): bool => ! ((bool) $get('is_selected')))
                                            ->columnSpan(3),

                                        TextInput::make('tax_percent')
                                            ->label('Tax %')
                                            ->numeric()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->disabled(fn (Get $get): bool => ! ((bool) $get('is_selected')))
                                            ->columnSpan(1),

                                        TextInput::make('grand_total')
                                            ->label('Amount Incl. Tax')
                                            ->numeric()
                                            ->readOnly()
                                            ->placeholder(function (Get $get) {
                                                if (! ((bool) $get('is_selected'))) {
                                                    return '0.00';
                                                }

                                                $qty = (float) ($get('quantity') ?? 0);
                                                $cost = (float) ($get('unit_cost') ?? 0);
                                                $tax = (float) ($get('tax_percent') ?? 0);
                                                $net = $qty * $cost;

                                                return number_format($net + ($net * ($tax / 100)), 2);
                                            })
                                            ->columnSpan(2),

                                        TextInput::make('line_total_preview')
                                            ->label('Net Amount')
                                            ->dehydrated(false)
                                            ->readOnly()
                                            ->placeholder(function (Get $get): string {
                                                if (! ((bool) $get('is_selected'))) {
                                                    return '0.00';
                                                }

                                                $qty = (float) ($get('quantity') ?? 0);
                                                $cost = (float) ($get('unit_cost') ?? 0);

                                                return number_format($qty * $cost, 2);
                                            })
                                            ->columnSpan(2),

                                        TextInput::make('tax_amount_preview')
                                            ->label('Tax Amount')
                                            ->dehydrated(false)
                                            ->readOnly()
                                            ->placeholder(function (Get $get): string {
                                                if (! ((bool) $get('is_selected'))) {
                                                    return '0.00';
                                                }

                                                $qty = (float) ($get('quantity') ?? 0);
                                                $cost = (float) ($get('unit_cost') ?? 0);
                                                $tax = (float) ($get('tax_percent') ?? 0);
                                                $net = $qty * $cost;

                                                return number_format($net * ($tax / 100), 2);
                                            })
                                            ->columnSpan(2),

                                        TextInput::make('item_code')->hidden(),
                                        TextInput::make('max_credit_quantity')->hidden(),
                                        TextInput::make('general_product_posting_group_id')->hidden(),
                                    ])
                                    ->columns(12)
                                    ->reorderable(false),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status & Dates')
                            ->schema([
                                Placeholder::make('status')
                                    ->content(fn ($record) => $record?->status?->getLabel() ?? 'Draft'),

                                Placeholder::make('rejection_reason')
                                    ->label('Rejection Reason')
                                    ->content(fn ($record) => $record?->rejection_reason)
                                    ->visible(fn ($record) => $record?->status === ApprovalStatus::REJECTED),

                                DatePicker::make('posting_date')
                                    ->default(now())
                                    ->required(),

                                Select::make('location_id')
                                    ->relationship('location', 'name')
                                    ->preload()
                                    ->searchable(),
                            ]),

                        Section::make('Financial Totals')
                            ->schema([
                                TextInput::make('grand_total')
                                    ->label('Grand Total')
                                    ->numeric()
                                    ->readOnly()
                                    ->placeholder(function (Get $get) {
                                        $lines = collect($get('lines'));
                                        $total = $lines->reduce(function ($carry, $line) {
                                            if (! ((bool) ($line['is_selected'] ?? true))) {
                                                return $carry;
                                            }

                                            $net = (float) ($line['quantity'] ?? 0) * (float) ($line['unit_cost'] ?? 0);
                                            $tax = $net * ((float) ($line['tax_percent'] ?? 0) / 100);

                                            return $carry + ($net + $tax);
                                        }, 0);

                                        return number_format($total, 2);
                                    }),
                                Select::make('currency_code')
                                    ->relationship('currency', 'code')
                                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->code)
                                    ->searchable()
                                    ->preload()
                                    ->default('NGN')
                                    ->required(),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }
}
