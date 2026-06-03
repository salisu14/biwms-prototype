<?php

namespace App\Filament\Resources\FixedAssets\Schemas;

use App\Enums\DepreciationMethod;
use App\Enums\FAStatus;
use App\Enums\FixedAssetType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class FixedAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Fixed Asset Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('General Information')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('fa_no')
                                        ->label('Asset No.')
                                        ->required()
                                        ->unique(ignoreRecord: true),
                                    TextInput::make('description')
                                        ->label('Main Description')
                                        ->required()
                                        ->columnSpan(2),
                                    TextInput::make('description_2')
                                        ->label('Alt. Description'),
                                    TextInput::make('search_description')
                                        ->label('Search Term'),
                                    Select::make('fa_type')
                                        ->label('Asset Type')
                                        ->options(FixedAssetType::class)
                                        ->required()
                                        ->native(false),
                                ]),

                                Grid::make(2)->schema([
                                    Select::make('fa_class_id')
                                        ->label('Asset Class')
                                        ->relationship('faClass', 'name')
                                        ->preload()
                                        ->searchable()
                                        ->reactive(),
                                    Select::make('fa_subclass_id')
                                        ->label('Asset Subclass')
                                        ->relationship('faSubclass', 'name', fn($query, $get) => $query->where('fa_class_id', $get('fa_class_id')))
                                        ->preload()
                                        ->searchable()
                                        ->disabled(fn($get) => !$get('fa_class_id')),
                                ]),

                                Fieldset::make('Status & Blocking')
                                    ->schema([
                                        Select::make('status')
                                            ->options(FAStatus::class)
                                            ->default(FAStatus::NEW)
                                            ->required(),
                                        Toggle::make('blocked')
                                            ->label('Blocked from Posting')
                                            ->reactive()
                                            ->inline(false),
                                        Textarea::make('blocked_reason')
                                            ->visible(fn($get) => $get('blocked'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Depreciation & Posting')
                            ->icon('heroicon-o-arrow-trending-down')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('depreciation_book_id')
                                        ->relationship('depreciationBook', 'code')
                                        ->required()
                                        ->searchable(),
                                    Select::make('fa_posting_group_id')
                                        ->relationship('postingGroup', 'code')
                                        ->required()
                                        ->searchable(),
                                    Select::make('depreciation_method')
                                        ->options(DepreciationMethod::class)
                                        ->required(),
                                    TextInput::make('depreciation_rate')
                                        ->numeric()
                                        ->suffix('%'),
                                    TextInput::make('useful_life_years')
                                        ->numeric()
                                        ->label('Useful Life (Years)'),
                                    TextInput::make('useful_life_months')
                                        ->numeric()
                                        ->label('Useful Life (Months)'),
                                ]),
                                Fieldset::make('Dates')
                                    ->schema([
                                        DatePicker::make('acquisition_date'),
                                        DatePicker::make('depreciation_starting_date'),
                                        DatePicker::make('depreciation_ending_date'),
                                    ])->columns(3),
                            ]),

                        Tabs\Tab::make('Value & Costing')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('acquisition_cost')
                                        ->numeric()
                                        ->required()
                                        ->prefix('₦'),
                                    TextInput::make('salvage_value')
                                        ->numeric()
                                        ->prefix('₦'),
                                    TextInput::make('salvage_value_percentage')
                                        ->numeric()
                                        ->suffix('%'),
                                    TextInput::make('book_value')
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->helperText('Calculated field'),
                                    TextInput::make('accumulated_depreciation')
                                        ->numeric()
                                        ->default(0),
                                    TextInput::make('revaluation_reserve')
                                        ->numeric()
                                        ->default(0),
                                ]),
                                Section::make('Acquisition Source')
                                    ->schema([
                                        Select::make('acquisition_vendor_id')
                                            ->relationship('vendor', 'vendor_name')
                                            ->searchable(),
                                        TextInput::make('acquisition_invoice_no'),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Physical & Tracking')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('serial_no'),
                                    TextInput::make('barcode'),
                                    Select::make('location_id')
                                        ->relationship('location', 'name')
                                        ->searchable(),
                                    TextInput::make('fa_location_code'),
                                    Select::make('responsible_employee_id')
                                        ->relationship('responsibleEmployee', 'name')
                                        ->searchable(),
                                ]),
                            ]),

                        Tabs\Tab::make('Insurance & Disposal')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Insurance')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('insurance_policy_no'),
                                        TextInput::make('insurance_value')->numeric()->prefix('₦'),
                                        DatePicker::make('insurance_expiry_date'),
                                    ]),
                                Section::make('Disposal')
                                    ->columns(3)
                                    ->schema([
                                        DatePicker::make('disposal_date'),
                                        TextInput::make('disposal_proceeds')->numeric()->prefix('₦'),
                                        TextInput::make('disposal_cost')->numeric()->prefix('₦'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
