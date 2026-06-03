<?php

namespace App\Filament\Resources\PurchaseReceipts\RelationManagers;

use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Models\Bin;
use App\Models\GlAccount;
use App\Models\Item;
use App\Models\ItemCharge;
use App\Models\PurchaseReceiptLine;
use App\Models\VatPostingSetup;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $relatedResource = PurchaseReceiptResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('ReceiptLineTabs')
                    ->tabs([
                        // ──────────────────────────────────────────
                        //  TAB 1 — GENERAL
                        // ──────────────────────────────────────────
                        Tabs\Tab::make('General')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Line Identification')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('line_number')
                                                    ->label('Line No.')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->default(
                                                        static::getNextLineNumber(
                                                            $schema->getRecord()?->purchase_receipt_id
                                                        )
                                                    )
                                                    ->required()
                                                    ->suffixIcon('heroicon-o-hashtag'),

                                                Select::make('type')
                                                    ->options([
                                                        'ITEM' => 'Item',
                                                        'GL' => 'G/L Account',
                                                        'CHARGE' => 'Charge (Item)',
                                                    ])
                                                    ->default('ITEM')
                                                    ->live()
                                                    ->required()
                                                    ->afterStateUpdated(
                                                        fn (Set $set) => $set('no', null)
                                                    )
                                                    ->native(false),
                                            ]),

                                        Grid::make(4)
                                            ->schema([
                                                Select::make('no')
                                                    ->label(fn (Get $get) => match ($get('type')) {
                                                        'ITEM' => 'Item No.',
                                                        'GL' => 'G/L Account No.',
                                                        'CHARGE' => 'Charge No.',
                                                        default => 'No.',
                                                    })
                                                    ->searchable()
                                                    ->required()
                                                    ->live()
                                                    ->getSearchResultsUsing(
                                                        fn (Get $get, string $search) => match ($get('type')) {
                                                            'ITEM' => Item::where('item_code', 'like', "%{$search}%")
                                                                ->orWhere('description', 'like', "%{$search}%")
                                                                ->limit(50)
                                                                ->get()
                                                                ->mapWithKeys(fn ($i) => [
                                                                    $i->item_code => "{$i->item_code} — {$i->description}",
                                                                ]),
                                                            'GL' => GlAccount::where('account_no', 'like', "%{$search}%")
                                                                ->orWhere('account_name', 'like', "%{$search}%")
                                                                ->limit(50)
                                                                ->get()
                                                                ->mapWithKeys(fn ($a) => [
                                                                    $a->account_no => "{$a->account_no} — {$a->account_name}",
                                                                ]),
                                                            'CHARGE' => ItemCharge::where('number', 'like', "%{$search}%")
                                                                ->orWhere('description', 'like', "%{$search}%")
                                                                ->limit(50)
                                                                ->get()
                                                                ->mapWithKeys(fn ($c) => [
                                                                    $c->number => "{$c->number} — {$c->description}",
                                                                ]),
                                                            default => [],
                                                        }
                                                    )
                                                    ->getOptionLabelUsing(
                                                        fn (Get $get, ?string $state) => $state
                                                            ? "{$state} — ".match ($get('type')) {
                                                                'ITEM' => Item::where('item_code', $state)->value('description') ?? '',
                                                                'GL' => GlAccount::where('account_no', $state)->value('account_name') ?? '',
                                                                'CHARGE' => ItemCharge::where('number', $state)->value('description') ?? '',
                                                                default => '',
                                                            }
                                                        : ''
                                                    )
                                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                        if (! $state || $get('type') !== 'ITEM') {
                                                            return;
                                                        }
                                                        $item = Item::query()
                                                            ->with('baseUom')
                                                            ->where('item_code', $state)
                                                            ->first();
                                                        if ($item) {
                                                            $set('description', $item->description);
                                                            $set('unit_of_measure_code', $item->baseUom?->uom_code);
                                                            $set('direct_unit_cost', $item->unit_cost);
                                                        }
                                                    }),

                                                TextInput::make('description')
                                                    ->label('Description')
                                                    ->required()
                                                    ->maxLength(100)
                                                    ->columnSpan(2),
                                            ]),

                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('description_2')
                                                    ->label('Description 2')
                                                    ->maxLength(50)
                                                    ->placeholder('Additional description'),

                                                TextInput::make('variant_code')
                                                    ->label('Variant Code')
                                                    ->maxLength(20)
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                Select::make('unit_of_measure_code')
                                                    ->label('Unit of Measure')
                                                    ->searchable()
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                TextInput::make('qty_per_unit_of_measure')
                                                    ->label('Qty per UoM')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM')
                                                    ->helperText('Auto-filled from item'),
                                            ]),
                                    ]),
                            ]),

                        // ──────────────────────────────────────────
                        //  TAB 2 — QUANTITIES & DATES
                        // ──────────────────────────────────────────
                        Tabs\Tab::make('Quantities & Dates')
                            ->icon('heroicon-o-scale')
                            ->schema([
                                Section::make('Quantities')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('quantity')
                                                    ->label('Quantity')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(0)
                                                    ->required()
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(
                                                        fn (Set $set, Get $get) => self::recalculateLine($set, $get)
                                                    ),

                                                TextInput::make('quantity_received')
                                                    ->label('Qty. Received')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(0)
                                                    ->disabled(fn (string $context) => $context === 'create')
                                                    ->dehydrated(),

                                                TextInput::make('quantity_invoiced')
                                                    ->label('Qty. Invoiced')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(0)
                                                    ->disabled()
                                                    ->dehydrated(),

                                                Placeholder::make('remaining_quantity_display')
                                                    ->label('Remaining Qty.')
                                                    ->content(fn (Get $get): string => number_format(
                                                        max(0, ($get('quantity') ?? 0) - ($get('quantity_received') ?? 0)),
                                                        4
                                                    ))
                                                    ->extraAttributes(['class' => 'fi-input-text-gray-500']),
                                            ]),

                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('quantity_base')
                                                    ->label('Quantity (Base)')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                TextInput::make('qty_received_base')
                                                    ->label('Qty. Received (Base)')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                TextInput::make('qty_invoiced_base')
                                                    ->label('Qty. Invoiced (Base)')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                TextInput::make('item_charge_base_amount')
                                                    ->label('Charge Base Amount')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->visible(fn (Get $get) => $get('type') === 'CHARGE'),
                                            ]),
                                    ]),

                                Section::make('Location')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Select::make('location_code')
                                                    ->label('Location Code')
                                                    ->relationship('location', 'code')
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('bin_code', null)),

                                                Select::make('bin_code')
                                                    ->label('Bin Code')
                                                    ->searchable()
                                                    ->options(
                                                        fn (Get $get) => $get('bin_code')
                                                            ? Bin::where('bin_code', $get('bin_code'))
                                                                ->pluck('bin_code', 'bin_code')
                                                            : []
                                                    ),

                                                //                                                Placeholder::make('whse_posting_group_display')
                                                //                                                    ->label('Whse. Posting Group')
                                                //                                                    ->content(fn (Get $get) => $get('type') === 'ITEM'
                                                //                                                        ? \App\Models\Item::where('no', $get('no'))->value('whse_posting_group') ?? '—'
                                                //                                                        : '—'
                                                //                                                    )
                                                //                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),
                                            ]),
                                    ]),

                                Section::make('Receipt Dates')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                DatePicker::make('expected_receipt_date')
                                                    ->label('Expected Receipt Date')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->default(now()->addDays(7)),

                                                DatePicker::make('planned_receipt_date')
                                                    ->label('Planned Receipt Date')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y'),

                                                DatePicker::make('requested_receipt_date')
                                                    ->label('Requested Receipt Date')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y'),

                                                DatePicker::make('promised_receipt_date')
                                                    ->label('Promised Receipt Date')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y'),
                                            ]),
                                    ]),
                            ]),

                        // ──────────────────────────────────────────
                        //  TAB 3 — PRICING & DISCOUNT
                        // ──────────────────────────────────────────
                        Tabs\Tab::make('Pricing & Discount')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Section::make('Pricing')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('direct_unit_cost')
                                                    ->label('Direct Unit Cost')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->prefix('$')
                                                    ->default(0)
                                                    ->required()
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(
                                                        fn (Set $set, Get $get) => self::recalculateLine($set, $get)
                                                    ),

                                                TextInput::make('unit_cost_lcy')
                                                    ->label('Unit Cost (LCY)')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->helperText('Auto-calculated from currency'),

                                                Placeholder::make('line_amount_display')
                                                    ->label('Line Amount')
                                                    ->content(fn (Get $get): string => '$'.number_format(
                                                        self::computeLineAmount($get), 2
                                                    ))
                                                    ->extraAttributes([
                                                        'class' => 'text-lg font-semibold text-primary-600',
                                                    ]),

                                                Toggle::make('allow_invoice_disc')
                                                    ->label('Allow Invoice Discount')
                                                    ->default(true)
                                                    ->inline(false),
                                            ]),
                                    ]),

                                Section::make('Discounts')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('line_discount_percent')
                                                    ->label('Line Discount %')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->suffix('%')
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                                        $cost = (float) ($get('direct_unit_cost') ?? 0);
                                                        $qty = (float) ($get('quantity') ?? 0);
                                                        $pct = (float) ($get('line_discount_percent') ?? 0);
                                                        $discAmt = $cost * $qty * $pct / 100;
                                                        $set('line_discount_amount', round($discAmt, 4));
                                                        self::recalculateLine($set, $get);
                                                    }),

                                                TextInput::make('line_discount_amount')
                                                    ->label('Line Discount Amount')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->prefix('$')
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                                        $cost = (float) ($get('direct_unit_cost') ?? 0);
                                                        $qty = (float) ($get('quantity') ?? 0);
                                                        $discAmt = (float) ($get('line_discount_amount') ?? 0);
                                                        if ($cost * $qty > 0) {
                                                            $set('line_discount_percent', round($discAmt / ($cost * $qty) * 100, 2));
                                                        }
                                                        self::recalculateLine($set, $get);
                                                    }),

                                                TextInput::make('inv_discount_amount')
                                                    ->label('Invoice Discount Amount')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->prefix('$')
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ]),
                                    ]),
                            ]),

                        // ──────────────────────────────────────────
                        //  TAB 4 — TAX & VAT
                        // ──────────────────────────────────────────
                        Tabs\Tab::make('Tax & VAT')
                            ->icon('heroicon-o-receipt-percent')
                            ->schema([
                                Section::make('Tax')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                Toggle::make('tax_liable')
                                                    ->label('Tax Liable')
                                                    ->inline(false),

                                                TextInput::make('tax_area_code')
                                                    ->label('Tax Area Code')
                                                    ->maxLength(20),

                                                TextInput::make('tax_group_code')
                                                    ->label('Tax Group Code')
                                                    ->maxLength(20),

                                                TextInput::make('use_tax')
                                                    ->label('Use Tax')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ]),
                                    ]),

                                Section::make('VAT')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                Select::make('vat_bus_posting_group')
                                                    ->label('VAT Bus. Posting Group')
                                                    ->options(
                                                        VatPostingSetup::distinct()
                                                            ->pluck('vat_business_posting_group_id', 'vat_business_posting_group_id')
                                                    )
                                                    ->searchable(),

                                                Select::make('vat_prod_posting_group')
                                                    ->label('VAT Prod. Posting Group')
                                                    ->options(
                                                        fn (Get $get) => $get('vat_bus_posting_group')
                                                            ? VatPostingSetup::where(
                                                                'vat_bus_posting_group', $get('vat_bus_posting_group')
                                                            )->pluck('vat_prod_posting_group', 'vat_prod_posting_group')
                                                            : []
                                                    )
                                                    ->searchable(),

                                                TextInput::make('vat_base_amount')
                                                    ->label('VAT Base Amount')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->disabled()
                                                    ->dehydrated(),

                                                TextInput::make('vat_difference')
                                                    ->label('VAT Difference')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ]),
                                    ]),
                            ]),

                        // ──────────────────────────────────────────
                        //  TAB 5 — DIMENSIONS & JOB
                        // ──────────────────────────────────────────
                        Tabs\Tab::make('Dimensions & Job')
                            ->icon('heroicon-o-square-3-stack-3d')
                            ->schema([
                                Section::make('Dimensions')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('shortcut_dimension_1_code')
                                                    ->label('Global Dimension 1')
                                                    ->maxLength(20),

                                                TextInput::make('shortcut_dimension_2_code')
                                                    ->label('Global Dimension 2')
                                                    ->maxLength(20),

                                                TextInput::make('dimension_set_id')
                                                    ->label('Dimension Set ID')
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ]),
                                    ]),

                                Section::make('Job')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('job_no')
                                                    ->label('Job No.')
                                                    ->maxLength(20),

                                                TextInput::make('job_task_no')
                                                    ->label('Job Task No.')
                                                    ->maxLength(20),

                                                TextInput::make('job_line_amount')
                                                    ->label('Job Line Amount')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->disabled()
                                                    ->dehydrated(),

                                                TextInput::make('job_currency_code')
                                                    ->label('Job Currency Code')
                                                    ->maxLength(10),
                                            ]),
                                    ]),
                            ]),

                        // ──────────────────────────────────────────
                        //  TAB 6 — PREPAYMENT & OTHERS
                        // ──────────────────────────────────────────
                        Tabs\Tab::make('Other Details')
                            ->icon('heroicon-o-ellipsis-horizontal-circle')
                            ->schema([
                                Section::make('Prepayment')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('prepayment_percent')
                                                    ->label('Prepayment %')
                                                    ->numeric()
                                                    ->suffix('%')
                                                    ->minValue(0)
                                                    ->maxValue(100),

                                                TextInput::make('prepmt_line_amount')
                                                    ->label('Prepmt. Line Amount')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->disabled()
                                                    ->dehydrated(),

                                                TextInput::make('prepmt_amt_inv')
                                                    ->label('Prepmt. Amt. Invoiced')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->disabled()
                                                    ->dehydrated(),

                                                TextInput::make('prepmt_amt_incl_vat')
                                                    ->label('Prepmt. Amt. Incl. VAT')
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ]),
                                    ]),

                                Section::make('Item Tracking & Physical')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('gross_weight')
                                                    ->label('Gross Weight')
                                                    ->numeric()
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                TextInput::make('net_weight')
                                                    ->label('Net Weight')
                                                    ->numeric()
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                TextInput::make('units_per_parcel')
                                                    ->label('Units per Parcel')
                                                    ->numeric()
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                TextInput::make('unit_volume')
                                                    ->label('Unit Volume')
                                                    ->numeric()
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),
                                            ]),

                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('cross_reference_no')
                                                    ->label('Cross Reference No.')
                                                    ->maxLength(20)
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                Select::make('cross_reference_type')
                                                    ->label('Cross Ref. Type')
                                                    ->options([
                                                        'BAR CODE' => 'Bar Code',
                                                        'VENDOR' => 'Vendor',
                                                        'CUSTOMER' => 'Customer',
                                                    ])
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                TextInput::make('item_category_code')
                                                    ->label('Item Category Code')
                                                    ->maxLength(20)
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),

                                                TextInput::make('product_group_code')
                                                    ->label('Product Group Code')
                                                    ->maxLength(20)
                                                    ->visible(fn (Get $get) => $get('type') === 'ITEM'),
                                            ]),
                                    ]),

                                Section::make('Shipment & Trade')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('transaction_type')
                                                    ->label('Transaction Type')
                                                    ->maxLength(10),

                                                TextInput::make('transport_method')
                                                    ->label('Transport Method')
                                                    ->maxLength(10),

                                                TextInput::make('entry_point')
                                                    ->label('Entry Point')
                                                    ->maxLength(10),

                                                TextInput::make('area')
                                                    ->label('Area')
                                                    ->maxLength(10),

                                                TextInput::make('transaction_specification')
                                                    ->label('Transaction Specification')
                                                    ->maxLength(10),

                                                Toggle::make('correction')
                                                    ->label('Correction')
                                                    ->inline(false),

                                                Toggle::make('system_created_entry')
                                                    ->label('System Created')
                                                    ->inline(false)
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    // ─── Helper: Recalculate line amount ───────────────────────
    private static function recalculateLine(Set $set, Get $get): void
    {
        $cost = (float) ($get('direct_unit_cost') ?? 0);
        $qty = (float) ($get('quantity') ?? 0);
        $discPct = (float) ($get('line_discount_percent') ?? 0);

        $discAmt = $cost * $qty * $discPct / 100;
        $lineAmt = ($cost * $qty) - $discAmt;

        $set('line_amount', round($lineAmt, 4));
        $set('line_discount_amount', round($discAmt, 4));
    }

    private static function computeLineAmount(Get $get): float
    {
        $cost = (float) ($get('direct_unit_cost') ?? 0);
        $qty = (float) ($get('quantity') ?? 0);
        $discPct = (float) ($get('line_discount_percent') ?? 0);
        $discAmt = (float) ($get('line_discount_amount') ?? 0) ?: ($cost * $qty * $discPct / 100);

        return round(($cost * $qty) - $discAmt, 4);
    }

    private static function getNextLineNumber(?int $receiptId): int
    {
        if (! $receiptId) {
            return 10000;
        }

        $max = PurchaseReceiptLine::query()
            ->where('purchase_receipt_id', $receiptId)
            ->max('line_number');

        return $max ? $max + 10000 : 10000;
    }

    // ═══════════════════════════════════════════════════════════
    //  TABLE
    // ═══════════════════════════════════════════════════════════
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_number')
                    ->label('Line')
                    ->sortable()
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->toggleable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ITEM' => 'success',
                        'GL' => 'warning',
                        'CHARGE' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ITEM' => 'Item',
                        'GL' => 'G/L',
                        'CHARGE' => 'Charge',
                        default => $state,
                    })
                    ->toggleable(),

                TextColumn::make('no')
                    ->label('No.')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copied!')
                    ->tooltip(fn ($record) => $record->description_2)
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description.($record->description_2 ? "\n".$record->description_2 : ''))
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('location_code')
                    ->label('Location')
                    ->badge()
                    ->color('gray')
                    ->toggleable()
                    ->visible(fn () => auth()->user()?->can('view_location_receipt_lines')),

                TextColumn::make('quantity')
                    ->label('Qty. Ordered')
                    ->numeric(decimalPlaces: 0)
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total'))
                    ->toggleable(),

                TextColumn::make('quantity_received')
                    ->label('Qty. Received')
                    ->numeric(decimalPlaces: 0)
                    ->alignEnd()
                    ->sortable()
                    ->color(function ($record) {
                        if ($record->quantity > 0 && $record->quantity_received >= $record->quantity) {
                            return 'success';
                        }
                        if ($record->quantity_received > 0) {
                            return 'warning';
                        }

                        return 'danger';
                    })
                    ->icon(function ($record) {
                        if ($record->quantity > 0 && $record->quantity_received >= $record->quantity) {
                            return 'heroicon-o-check-circle';
                        }
                        if ($record->quantity_received > 0) {
                            return 'heroicon-o-clock';
                        }

                        return 'heroicon-o-x-circle';
                    })
                    ->iconPosition(IconPosition::After)
                    ->summarize(Sum::make()->label('Received'))
                    ->toggleable(),

                TextColumn::make('remaining_qty')
                    ->label('Remaining')
                    ->state(fn ($record) => $record->getRemainingQuantity())
                    ->numeric(decimalPlaces: 0)
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->toggleable(),

                TextColumn::make('quantity_invoiced')
                    ->label('Qty. Invoiced')
                    ->numeric(decimalPlaces: 0)
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('direct_unit_cost')
                    ->label('Unit Cost')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('line_discount_percent')
                    ->label('Disc. %')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('line_amount')
                    ->label('Line Amount')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->summarize(Sum::make()->label('Total')->money('USD'))
                    ->toggleable(),

                TextColumn::make('expected_receipt_date')
                    ->label('Expected Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('receipt_status')
                    ->label('Status')
                    ->state(fn ($record) => $record->isFullyReceived() ? 'complete' : ($record->isPartiallyReceived() ? 'partial' : 'pending'))
                    ->icon(fn (string $state): string => match ($state) {
                        'complete' => 'heroicon-o-check-badge',
                        'partial' => 'heroicon-o-clock',
                        'pending' => 'heroicon-o-exclamation-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'complete' => 'success',
                        'partial' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => ucfirst($state))
                    ->toggleable(),
            ])
            ->defaultSort('line_number', 'asc')
            ->reorderable('line_number')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'ITEM' => 'Item',
                        'GL' => 'G/L Account',
                        'CHARGE' => 'Charge',
                    ])
                    ->label('Line Type'),

                SelectFilter::make('location_code')
                    ->relationship('location', 'code')
                    ->label('Location')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('receipt_status')
                    ->label('Receipt Status')
                    ->placeholder('All')
                    ->trueLabel('Fully Received')
                    ->falseLabel('Not Received')
                    ->queries(
                        true: fn ($query) => $query->whereColumn('quantity_received', '>=', 'quantity'),
                        false: fn ($query) => $query->where('quantity_received', 0),
                    ),

                TernaryFilter::make('invoice_status')
                    ->label('Invoice Status')
                    ->placeholder('All')
                    ->trueLabel('Fully Invoiced')
                    ->falseLabel('Not Invoiced')
                    ->queries(
                        true: fn ($query) => $query->whereColumn('quantity_invoiced', '>=', 'quantity'),
                        false: fn ($query) => $query->where('quantity_invoiced', 0),
                    ),

                Filter::make('partially_received')
                    ->label('Partially Received')
                    ->query(fn ($query) => $query->whereColumn('quantity_received', '>', 0)->whereColumn('quantity_received', '<', 'quantity'))
                    ->toggle(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        if (empty($data['line_number'])) {
                            $data['line_number'] = self::getNextLineNumber($data['purchase_receipt_id'] ?? null);
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

                Action::make('mark_received')
                    ->label('Mark Received')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark line as fully received')
                    ->modalDescription('This will set Qty. Received equal to Quantity.')
                    ->visible(fn ($record) => ! $record->isFullyReceived() && $record->quantity > 0)
                    ->action(function ($record) {
                        $remaining = $record->getRemainingQuantity();
                        $record->updateReceivedQuantity($remaining);
                        Notification::make()
                            ->title('Line marked as received')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('mark_received_bulk')
                        ->label('Mark Selected as Received')
                        ->icon('heroicon-o-check')
                        ->requiresConfirmation()
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if (! $record->isFullyReceived()) {
                                    $record->updateReceivedQuantity($record->getRemainingQuantity());
                                }
                            }
                            Notification::make()
                                ->title('Selected lines marked as received')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No receipt lines')
            ->emptyStateDescription('Create the first line for this purchase receipt.')
            ->emptyStateIcon('heroicon-o-document-plus');
    }
}
