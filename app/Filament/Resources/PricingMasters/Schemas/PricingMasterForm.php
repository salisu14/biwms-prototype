<?php

namespace App\Filament\Resources\PricingMasters\Schemas;

use App\Models\Customer;
use App\Models\Item;
use App\Models\PricingGroup;
use App\Models\UnitOfMeasure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PricingMasterForm
{
    private static function currencySymbol(?string $currencyCode): string
    {
        return match ($currencyCode) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => '₦',
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Pricing Master')
                    ->tabs([
                        Tab::make('General & Scope')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Identification')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('price_list_code')
                                                ->label('Price List Code')
                                                ->required()
                                                ->unique(ignoreRecord: true)
                                                ->maxLength(20)
                                                ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                            TextInput::make('description')
                                                ->label('Description')
                                                ->required()
                                                ->maxLength(100)
                                                ->columnSpan(2),
                                        ]),
                                    ]),
                                Section::make('Applicability & Scope')
                                    ->description('Define who and what this price applies to.')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('price_list_type')
                                                ->label('Price List Type')
                                                ->options([
                                                    'ALL_CUSTOMERS' => 'All Customers',
                                                    'CUSTOMER' => 'Specific Customer',
                                                    'CUSTOMER_GROUP' => 'Customer Group',
                                                    'CAMPAIGN' => 'Campaign / Promo',
                                                    'TRANSFER' => 'Transfer',
                                                ])
                                                ->required()
                                                ->default('ALL_CUSTOMERS')
                                                ->live()
                                                ->native(false),

                                            Select::make('customer_id')
                                                ->label('Customer')
                                                ->relationship('customer', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->getSearchResultsUsing(
                                                    fn (string $search) => Customer::query()
                                                        ->where(function ($query) use ($search) {
                                                            $query->where('customer_number', 'like', "%{$search}%")
                                                                ->orWhere('name', 'like', "%{$search}%");
                                                        })
                                                        ->limit(50)
                                                        ->get()
                                                        ->mapWithKeys(fn (Customer $record) => [
                                                            $record->id => "{$record->customer_number} — {$record->name}",
                                                        ])
                                                )
                                                ->getOptionLabelFromRecordUsing(
                                                    fn (Customer $record) => "{$record->customer_number} — {$record->name}"
                                                )
                                                ->visible(fn (Get $get) => $get('price_list_type') === 'CUSTOMER')
                                                ->required(fn (Get $get) => $get('price_list_type') === 'CUSTOMER'),

                                            Select::make('pricing_group_id')
                                                ->label('Pricing Group')
                                                ->relationship('pricingGroup', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->getSearchResultsUsing(
                                                    fn (string $search) => PricingGroup::query()
                                                        ->where(function ($query) use ($search) {
                                                            $query->where('code', 'like', "%{$search}%")
                                                                ->orWhere('name', 'like', "%{$search}%");
                                                        })
                                                        ->limit(50)
                                                        ->get()
                                                        ->mapWithKeys(fn (PricingGroup $record) => [
                                                            $record->id => "{$record->code} — {$record->name}",
                                                        ])
                                                )
                                                ->getOptionLabelFromRecordUsing(
                                                    fn (PricingGroup $record) => "{$record->code} — {$record->name}"
                                                )
                                                ->visible(fn (Get $get) => in_array($get('price_list_type'), ['CUSTOMER_GROUP', 'CAMPAIGN']))
                                                ->required(fn (Get $get) => $get('price_list_type') === 'CUSTOMER_GROUP'),
                                        ]),
                                        Grid::make(4)->schema([
                                            Select::make('item_id')
                                                ->label('Item')
                                                ->relationship(
                                                    name: 'item',
                                                    titleAttribute: 'description',
                                                    modifyQueryUsing: fn ($query) => $query->finishedGoods()->where('blocked', false)->orderBy('item_code')
                                                )
                                                ->searchable()
//                                                ->searchColumns(['item_code', 'description'])
                                                ->getSearchResultsUsing(
                                                    fn (string $search) => Item::query()
                                                        ->finishedGoods()
                                                        ->where('blocked', false)
                                                        ->where(function ($query) use ($search) {
                                                            $query->where('item_code', 'like', "%{$search}%")
                                                                ->orWhere('description', 'like', "%{$search}%");
                                                        })
                                                        ->limit(50)
                                                        ->get()
                                                        ->mapWithKeys(fn (Item $record) => [
                                                            $record->id => "{$record->item_code} — {$record->description}",
                                                        ])
                                                )
                                                ->getOptionLabelFromRecordUsing(fn (Item $record) => "{$record->item_code} — {$record->description}")
                                                ->preload()
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function (Set $set) {
                                                    $set('unit_of_measure_code', null);
                                                }),

                                            TextInput::make('variant_code')
                                                ->label('Variant Code')
                                                ->maxLength(20),

                                            Select::make('unit_of_measure_code')
                                                ->label('Unit of Measure')
                                                ->options(function (Get $get) {
                                                    $itemId = $get('item_id');

                                                    if (! $itemId) {
                                                        return [];
                                                    }

                                                    $item = Item::query()
                                                        ->with(['baseUom', 'uoms'])
                                                        ->find($itemId);

                                                    if (! $item) {
                                                        return [];
                                                    }

                                                    $options = $item->uoms()
                                                        ->get()
                                                        ->mapWithKeys(fn (UnitOfMeasure $uom) => [
                                                            $uom->uom_code => $uom->uom_code.($uom->description ? " — {$uom->description}" : ''),
                                                        ])
                                                        ->toArray();

                                                    $baseUomCode = $item->baseUom?->uom_code;
                                                    if ($baseUomCode && ! array_key_exists($baseUomCode, $options)) {
                                                        $options[$baseUomCode] = $baseUomCode.($item->baseUom?->description ? " — {$item->baseUom->description}" : '');
                                                    }

                                                    return $options;
                                                })
                                                ->searchable()
                                                ->live()
                                                ->helperText('Only UoMs assigned to the selected item are shown.'),

                                            Select::make('currency_code')
                                                ->label('Currency')
                                                ->options(['NGN' => 'NGN (₦)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)'])
                                                ->required()
                                                ->live()
                                                ->default('NGN')
                                                ->native(false),
                                        ]),
                                        Grid::make(2)->schema([
                                            Select::make('location_id')
                                                ->label('Location (Optional)')
                                                ->relationship('location', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->helperText('Leave empty to apply to all locations.'),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Pricing & Quantities')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Section::make('Price Calculation')
                                    ->schema([
                                        Select::make('price_type')
                                            ->label('Pricing Method')
                                            ->options([
                                                'UNIT_PRICE' => 'Fixed Unit Price',
                                                'PERCENT_DISCOUNT' => 'Discount %',
                                                'AMOUNT_DISCOUNT' => 'Discount Amount',
                                                'COST_PLUS_PERCENT' => 'Cost + %',
                                                'COST_PLUS_AMOUNT' => 'Cost + Amount',
                                                'FORMULA' => 'Formula',
                                            ])
                                            ->required()
                                            ->default('UNIT_PRICE')
                                            ->live()
                                            ->native(false),

                                        Grid::make(4)->schema([
                                            TextInput::make('unit_price')
                                                ->label('Unit Price')
                                                ->numeric()
                                                ->prefix(fn (Get $get) => self::currencySymbol($get('currency_code')))
                                                ->minValue(0)
                                                ->step(0.0001)
                                                ->visible(fn (Get $get) => $get('price_type') === 'UNIT_PRICE')
                                                ->required(fn (Get $get) => $get('price_type') === 'UNIT_PRICE'),

                                            TextInput::make('discount_percent')
                                                ->label('Discount %')
                                                ->numeric()
                                                ->suffix('%')
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->step(0.01)
                                                ->visible(fn (Get $get) => $get('price_type') === 'PERCENT_DISCOUNT')
                                                ->required(fn (Get $get) => $get('price_type') === 'PERCENT_DISCOUNT'),

                                            TextInput::make('discount_amount')
                                                ->label('Discount Amount')
                                                ->numeric()
                                                ->prefix(fn (Get $get) => self::currencySymbol($get('currency_code')))
                                                ->minValue(0)
                                                ->step(0.0001)
                                                ->visible(fn (Get $get) => in_array($get('price_type'), ['AMOUNT_DISCOUNT']))
                                                ->required(fn (Get $get) => in_array($get('price_type'), ['AMOUNT_DISCOUNT'])),

                                            TextInput::make('cost_plus_percent')
                                                ->label('Cost Plus % / Amount')
                                                ->numeric()
                                                ->step(0.01)
                                                ->visible(fn (Get $get) => in_array($get('price_type'), ['COST_PLUS_PERCENT', 'COST_PLUS_AMOUNT']))
                                                ->required(fn (Get $get) => in_array($get('price_type'), ['COST_PLUS_PERCENT', 'COST_PLUS_AMOUNT']))
                                                ->suffix(fn (Get $get) => $get('price_type') === 'COST_PLUS_PERCENT' ? '%' : '₦'),
                                        ]),
                                    ]),

                                Section::make('Quantity Constraints')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            Toggle::make('allow_quantity_breaks')
                                                ->label('Allow Qty. Breaks')
                                                ->default(false)
                                                ->inline(false),

                                            TextInput::make('minimum_quantity')
                                                ->label('Min. Quantity')
                                                ->required()
                                                ->numeric()
                                                ->default(0)
                                                ->step(0.0001),

                                            TextInput::make('maximum_quantity')
                                                ->label('Max. Quantity')
                                                ->numeric()
                                                ->step(0.0001)
                                                ->minValue(fn (Get $get) => $get('minimum_quantity'))
                                                ->helperText('Leave empty for unlimited.'),

                                            TextInput::make('minimum_lead_time_days')
                                                ->label('Min. Lead Time (Days)')
                                                ->numeric()
                                                ->integer()
                                                ->minValue(0),
                                        ]),
                                        Grid::make(2)->schema([
                                            TextInput::make('minimum_order_amount')
                                                ->label('Min. Order Amount')
                                                ->numeric()
                                                ->prefix(fn (Get $get) => self::currencySymbol($get('currency_code')))
                                                ->step(0.01),

                                            TextInput::make('minimum_order_quantity')
                                                ->label('Min. Order Quantity')
                                                ->numeric()
                                                ->step(0.0001),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Schedule & Constraints')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Section::make('Date & Time Effectivity')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            DatePicker::make('start_date')
                                                ->label('Start Date')
                                                ->required()
                                                ->default(now())
                                                ->native(false)
                                                ->live(),

                                            DatePicker::make('end_date')
                                                ->label('End Date')
                                                ->native(false)
                                                ->minDate(fn (Get $get) => $get('start_date'))
                                                ->helperText('Leave empty for perpetual price.'),
                                        ]),
                                        Grid::make(2)->schema([
                                            TimePicker::make('start_time')
                                                ->label('Active From (Time)')
                                                ->native(false)
                                                ->helperText('Optional: Time of day price becomes active.'),

                                            TimePicker::make('end_time')
                                                ->label('Active Until (Time)')
                                                ->native(false),
                                        ]),
                                    ]),

                                Section::make('Applicable Days')
                                    ->description('Select which days of the week this price is valid for.')
                                    ->schema([
                                        CheckboxList::make('applicable_days')
                                            ->label('Days of Week')
                                            ->options([
                                                'mon' => 'Monday',
                                                'tue' => 'Tuesday',
                                                'wed' => 'Wednesday',
                                                'thu' => 'Thursday',
                                                'fri' => 'Friday',
                                                'sat' => 'Saturday',
                                                'sun' => 'Sunday',
                                            ])
                                            ->columns(4)
                                            ->bulkToggleable()
                                            ->helperText('Leave empty to apply to all days.'),
                                    ]),
                            ]),

                        Tab::make('Audit & Versioning')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make('Status & Priority')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('status')
                                                ->options([
                                                    'DRAFT' => 'Draft',
                                                    'PENDING_APPROVAL' => 'Pending Approval',
                                                    'ACTIVE' => 'Active',
                                                    'EXPIRED' => 'Expired',
                                                    'CANCELLED' => 'Cancelled',
                                                ])
                                                ->required()
                                                ->default('DRAFT')
                                                ->native(false),

                                            TextInput::make('priority')
                                                ->label('Priority')
                                                ->required()
                                                ->numeric()
                                                ->default(0)
                                                ->helperText('Higher priority overrides other prices.'),

                                            Toggle::make('is_current_version')
                                                ->label('Is Current Version')
                                                ->default(true)
                                                ->inline(false),
                                        ]),
                                    ]),

                                Section::make('Approval & Versioning')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('approved_by')
                                                ->label('Approved By')
                                                ->relationship('approvedByUser', 'name')
                                                ->searchable()
                                                ->disabled(),

                                            DateTimePicker::make('approved_at')
                                                ->label('Approved At')
                                                ->disabled(),
                                        ]),
                                        Grid::make(2)->schema([
                                            Select::make('replaces_id')
                                                ->label('Replaces Price List')
                                                ->relationship('replaces', 'price_list_code')
                                                ->searchable()
                                                ->preload(),

                                            Select::make('replaced_by_id')
                                                ->label('Replaced By Price List')
                                                ->relationship('replacedBy', 'price_list_code')
                                                ->searchable()
                                                ->preload(),
                                        ]),
                                    ]),

                                Section::make('Modification Log')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('created_by')->disabled()->dehydrated(false),
                                            TextInput::make('modified_by')->disabled()->dehydrated(false),
                                        ]),
                                        Textarea::make('modification_reason')
                                            ->label('Reason for Modification')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
