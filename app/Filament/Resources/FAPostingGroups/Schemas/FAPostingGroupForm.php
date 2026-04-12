<?php

namespace App\Filament\Resources\FAPostingGroups\Schemas;

use App\Enums\IntangibleAssetType;
use App\Enums\LiquidityAssetType;
use App\Enums\TangibleAssetType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class FAPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Fixed Asset Posting Setup')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-m-identification')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('code')
                                        ->label('Posting Group Code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                    TextInput::make('description')
                                        ->label('Description')
                                        ->maxLength(255)
                                        ->columnSpan(2),
                                ]),
                                Section::make('Status & Active Rules')
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label('Active Status')
                                            ->default(true)
                                            ->onColor('success'),
                                    ])->compact()->inlineLabel(),
                            ]),

                        Tabs\Tab::make('Acquisition & Depreciation')
                            ->icon('heroicon-m-calculator')
                            ->schema([
                                Section::make('Acquisition Posting')
                                    ->description('Accounts used during purchase or capitalization.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            self::getAccountSelect('acquisition_cost_account_id', 'acquisitionAccount', 'Acquisition Cost Account'),
                                            self::getAccountSelect('acquisition_cost_offset_account_id', 'acquisitionCostOffsetAccount', 'Acquisition Cost Offset'),
                                        ]),
                                    ]),
                                Section::make('Depreciation Posting')
                                    ->description('Accounts for accumulated depreciation and periodic expenses.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            self::getAccountSelect('depreciation_account_id', 'depreciationAccount', 'Accum. Depreciation Account'),
                                            self::getAccountSelect('depreciation_expense_account_id', 'depExpenseAccount', 'Depreciation Expense Account'),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Disposal & Maintenance')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Section::make('Disposal Posting')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            self::getAccountSelect('disposal_proceeds_account_id', 'disposalProceedsAccount', 'Disposal Proceeds Account'),
                                            self::getAccountSelect('gain_on_disposal_account_id', 'gainOnDisposalAccount', 'Gain on Disposal Account'),
                                            self::getAccountSelect('loss_on_disposal_account_id', 'lossOnDisposalAccount', 'Loss on Disposal Account'),
                                        ]),
                                    ]),
                                Section::make('Maintenance Posting')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            self::getAccountSelect('maintenance_expense_account_id', 'maintenanceExpenseAccount', 'Maintenance Expense Account'),
                                            self::getAccountSelect('maintenance_cost_account_id', 'maintenanceCostAccount', 'Maintenance Cost Account'),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Revaluation')
                            ->icon('heroicon-m-arrow-path')
                            ->schema([
                                Grid::make(2)->schema([
                                    self::getAccountSelect('appreciation_account_id', 'appreciationAccount', 'Appreciation Account'),
                                    self::getAccountSelect('revaluation_gain_account_id', 'revaluationGainAccount', 'Revaluation Gain Account'),
                                ]),
                            ]),

                        Tabs\Tab::make('Applicability')
                            ->icon('heroicon-m-check-badge')
                            ->schema([
                                Section::make('Asset Type Mapping')
                                    ->description('Define which asset types can utilize this posting group.')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            CheckboxList::make('applicable_tangible_types')
                                                ->label('Tangible Assets')
                                                ->options(TangibleAssetType::class)
                                                ->columns(1),
                                            CheckboxList::make('applicable_intangible_types')
                                                ->label('Intangible Assets')
                                                ->options(IntangibleAssetType::class)
                                                ->columns(1),
                                            CheckboxList::make('applicable_liquidity_types')
                                                ->label('Liquidity Assets')
                                                ->options(LiquidityAssetType::class)
                                                ->columns(1),
                                        ]),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    /**
     * Helper to create a standardized G/L Account selector
     */
    protected static function getAccountSelect(string $name, string $relationship, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->relationship($relationship, 'name')
            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->account_number} - {$record->name}")
            ->searchable()
            ->preload();
    }
}
