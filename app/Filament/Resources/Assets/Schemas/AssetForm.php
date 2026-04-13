<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\AssetType;
use App\Enums\FixedAssetCategory;
use App\Enums\IntangibleAssetType;
use App\Enums\LiquidityAssetType;
use App\Enums\TangibleAssetType;
use App\Models\Asset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)->schema([
                    Section::make('General Identification')
                        ->schema([
                            TextInput::make('asset_no')
                                ->label('Asset No.')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(50)
                                // Lock the field if the record already exists in the database
                                ->disabled(fn (?Asset $record) => $record !== null)
                                // Ensure the value is still sent to the database during creation
                                ->dehydrated()
                                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                ->helperText('The code cannot be changed once the Asset is created.'),
                            TextInput::make('description')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('description_2')
                                ->label('Alt. Description'),
                            TextInput::make('search_name')
                                ->label('Search Name'),
                        ])->columnSpan(2),

                    Section::make('System Controls')
                        ->schema([
                            Select::make('asset_type')
                                ->label('Primary Type')
                                ->options(AssetType::class)
                                ->required()
                                ->live()
                                ->native(false),
                            Select::make('currency_id')
                                ->relationship('currency', 'code')
                                ->label('Reporting Currency')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Toggle::make('active')
                                ->label('Operational Status')
                                ->default(true)
                                ->onColor('success'),
                        ])->columnSpan(1),
                ]),

                Tabs::make('Asset Profile')
                    ->tabs([
                        Tabs\Tab::make('Classification')
                            ->icon('heroicon-m-tag')
                            ->schema([
                                Grid::make(2)->schema([
                                    // Fixed Asset Specific
                                    Select::make('fixed_asset_category')
                                        ->label('Fixed Asset Category')
                                        ->options(FixedAssetCategory::class)
                                        ->visible(fn (Get $get) => $get('asset_type') === AssetType::FIXED->value)
                                        ->live()
                                        ->native(false),

                                    Select::make('tangible_type')
                                        ->label('Tangible Sub-Type')
                                        ->options(TangibleAssetType::class)
                                        ->visible(fn (Get $get) => $get('fixed_asset_category') === FixedAssetCategory::TANGIBLE->value)
                                        ->live(),

                                    Select::make('intangible_type')
                                        ->label('Intangible Sub-Type')
                                        ->options(IntangibleAssetType::class)
                                        ->visible(fn (Get $get) => $get('fixed_asset_category') === FixedAssetCategory::INTANGIBLE->value)
                                        ->live(),

                                    // Liquidity Specific
                                    Select::make('liquidity_type')
                                        ->label('Liquidity Account Type')
                                        ->options(LiquidityAssetType::class)
                                        ->visible(fn (Get $get) => $get('asset_type') === AssetType::LIQUIDITY->value)
                                        ->live()
                                        ->native(false),
                                ]),
                            ]),

                        Tabs\Tab::make('Financials & Valuation')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                // Fixed Asset Valuation
                                Section::make('Fixed Asset Valuation')
                                    ->visible(fn (Get $get) => $get('asset_type') === AssetType::FIXED->value)
                                    ->schema([
                                        Grid::make(3)->schema([
                                            DatePicker::make('acquisition_date')->label('Acquisition Date'),
                                            TextInput::make('acquisition_cost')->numeric()->prefix('$')->label('Original Cost'),
                                            TextInput::make('book_value')->numeric()->prefix('$')->label('Net Book Value')->disabled(),
                                            TextInput::make('salvage_value')->numeric()->prefix('$')->label('Residual Value'),
                                            TextInput::make('accumulated_depreciation')->numeric()->prefix('$')->disabled(),
                                            Toggle::make('acquired')->disabled()->inline(false),
                                        ]),
                                    ]),

                                // Liquidity Balances
                                Section::make('Account Balance')
                                    ->visible(fn (Get $get) => $get('asset_type') === AssetType::LIQUIDITY->value)
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('opening_balance')->numeric()->prefix('$'),
                                            TextInput::make('current_balance')->numeric()->prefix('$')->label('Current Ledger Balance'),
                                            DatePicker::make('last_reconciliation_date'),
                                            TextInput::make('currency_factor')->numeric()->step(0.000001)->default(1.0),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Specifications & Lifecycle')
                            ->icon('heroicon-m-wrench-screwdriver')
                            ->schema([
                                Section::make('Technical Details')
                                    ->visible(fn (Get $get) => $get('asset_type') === AssetType::FIXED->value)
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('fa_location_code')
                                                ->relationship('location', 'name')
                                                ->label('FixedAsset Location (Physical)')
                                                ->searchable()
                                                ->preload(),
                                            TextInput::make('serial_no')->label('Serial/Asset Tag No.'),
                                            TextInput::make('registration_no')->label('License/Reg No.'),
                                            Select::make('main_asset_id')
                                                ->relationship('mainAsset', 'description')
                                                ->label('Main Asset (if component)'),
                                        ]),
                                    ]),

                                Section::make('Depreciation Profile')
                                    ->visible(fn (Get $get) => $get('asset_type') === AssetType::FIXED->value)
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('depreciation_method')
                                                ->options(['STRAIGHT_LINE' => 'Straight Line', 'DECLINING_BALANCE' => 'Declining Balance']),
                                            DatePicker::make('depreciation_start_date'),
                                            DatePicker::make('depreciation_end_date'),
                                            TextInput::make('useful_life_months')->numeric()->label('Useful Life (Months)'),
                                            TextInput::make('depreciation_rate')->numeric()->suffix('%'),
                                            DatePicker::make('last_depreciation_date')->disabled(),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Relationships & Banking')
                            ->icon('heroicon-m-user-group')
                            ->schema([
                                Section::make('Banking Info')
                                    ->visible(fn (Get $get) => $get('liquidity_type') === LiquidityAssetType::CASH_BANK->value)
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('bank_account_id')->relationship('bankAccount', 'account_name'),
                                            TextInput::make('bank_name')->label('Bank Name'),
                                            TextInput::make('bank_account_no')->label('Account Number'),
                                            TextInput::make('account_holder_name')->label('Account Holder'),
                                            TextInput::make('branch_code')->label('Branch Code'),
                                            TextInput::make('iban')->label('IBAN'),
                                            TextInput::make('swift_code')->label('SWIFT/BIC'),
                                        ]),
                                    ]),

                                Section::make('Entity Sub-ledger Links')
                                    ->visible(fn (Get $get) => $get('asset_type') === AssetType::LIQUIDITY->value)
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('vendor_id')
                                                ->relationship('vendor', 'vendor_name')
                                                ->label('Affiliated Vendor')
                                                ->visible(fn (Get $get) => in_array($get('liquidity_type'), [
                                                    LiquidityAssetType::ADVANCE_VENDOR->value,
                                                    LiquidityAssetType::ACCOUNTS_RECEIVABLE->value ?? 'NOT_SET', // Just in case
                                                ])),
                                            Select::make('customer_id')
                                                ->relationship('customer', 'customer_name')
                                                ->label('Affiliated Customer')
                                                ->visible(fn (Get $get) => $get('liquidity_type') === LiquidityAssetType::ACCOUNTS_RECEIVABLE->value),
                                            Select::make('employee_id')
                                                ->relationship('employee', 'full_name') // Assuming full_name exists
                                                ->label('Affiliated Employee')
                                                ->visible(fn (Get $get) => $get('liquidity_type') === LiquidityAssetType::ADVANCE_STAFF->value),
                                            TextInput::make('reference_document_no')->label('External Ref No.'),
                                            DatePicker::make('expected_clearance_date')->label('Expected Settlement'),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Accounting & Posting')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('fa_posting_group_id')
                                        ->relationship('postingGroup', 'code')
                                        ->label('Posting Group Profile')
                                        ->required(),
                                    Select::make('asset_account_id')
                                        ->relationship('assetAccount', 'account_number')
                                        ->label('Balance Sheet Account'),
                                    Select::make('accum_dep_account_id')
                                        ->relationship('accumDepAccount', 'account_number')
                                        ->label('Accumulated Depreciation Account')
                                        ->visible(fn (Get $get) => $get('asset_type') === AssetType::FIXED->value),
                                    Select::make('depreciation_expense_account_id')
                                        ->relationship('depExpenseAccount', 'account_number')
                                        ->label('Depreciation Expense Account')
                                        ->visible(fn (Get $get) => $get('asset_type') === AssetType::FIXED->value),
                                    Select::make('gain_loss_account_id')
                                        ->relationship('gainLossAccount', 'account_number')
                                        ->label('Gain/Loss Account'),
                                ]),
                                Textarea::make('notes')->rows(3)->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
