<?php

declare(strict_types=1);

namespace App\Filament\Resources\FAPostingGroups\Schemas;

use App\Models\FAPostingGroup;
use Filament\Forms\Components\Select as FormSelect;
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
                                        ->maxLength(20)
                                        ->disabled(fn (?FAPostingGroup $record) => $record !== null)
                                        ->dehydrated()
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                        ->helperText('The code cannot be changed once created.'),

                                    TextInput::make('description')
                                        ->label('Description')
                                        ->required()
                                        ->maxLength(100)
                                        ->columnSpan(2),
                                ]),

                                Section::make('Status & Depreciation Defaults')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            Toggle::make('is_active')
                                                ->label('Active')
                                                ->default(true)
                                                ->onColor('success'),

                                            Toggle::make('auto_depreciate_acquisition_year')
                                                ->label('Depreciate in Acq. Year')
                                                ->default(true)
                                                ->onColor('success'),

                                            FormSelect::make('depreciation_calculation')
                                                ->label('Depreciation Calculation')
                                                ->options([
                                                    'full_year' => 'Full Year',
                                                    'pro_rata' => 'Pro Rata',
                                                    'half_year' => 'Half Year',
                                                ])
                                                ->default('pro_rata')
                                                ->native(false),

                                            FormSelect::make('depreciation_start')
                                                ->label('Depreciation Start')
                                                ->options([
                                                    'acquisition' => 'Acquisition Date',
                                                    'first_day_next_month' => 'First Day Next Month',
                                                ])
                                                ->default('acquisition')
                                                ->native(false),
                                        ]),
                                    ])->compact(),
                            ]),

                        Tabs\Tab::make('Acquisition & Depreciation')
                            ->icon('heroicon-m-calculator')
                            ->schema([
                                Section::make('Acquisition Posting')
                                    ->description('Accounts used during purchase or capitalization.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            self::accountSelect('acquisition_cost_account_id', 'acquisitionCostAccount', 'Acquisition Cost Account'),
                                            self::accountSelect('acquisition_cost_account_id_lcy', 'acquisitionCostAccountLcy', 'Acquisition Cost Account (LCY)'),
                                        ]),
                                    ]),

                                Section::make('Depreciation Posting')
                                    ->description('Accounts for accumulated depreciation and periodic expenses.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            self::accountSelect('depreciation_expense_account_id', 'depreciationExpenseAccount', 'Depreciation Expense Account'),
                                            self::accountSelect('accumulated_depreciation_account_id', 'accumulatedDepreciationAccount', 'Accumulated Depreciation Account'),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Disposal & Maintenance')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Section::make('Disposal Posting')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            self::accountSelect('disposal_proceeds_account_id', 'disposalProceedsAccount', 'Disposal Proceeds Account'),
                                            self::accountSelect('disposal_gain_account_id', 'disposalGainAccount', 'Disposal Gain Account'),
                                            self::accountSelect('disposal_loss_account_id', 'disposalLossAccount', 'Disposal Loss Account'),
                                        ]),
                                    ]),

                                Section::make('Maintenance & Capitalization')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            self::accountSelect('maintenance_expense_account_id', 'maintenanceExpenseAccount', 'Maintenance Expense Account'),
                                            self::accountSelect('capitalization_account_id', 'capitalizationAccount', 'Capitalization Account (CWIP)'),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Revaluation & Tax')
                            ->icon('heroicon-m-arrow-path')
                            ->schema([
                                Section::make('Revaluation')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            self::accountSelect('revaluation_account_id', 'revaluationAccount', 'Revaluation Account'),
                                            self::accountSelect('reversal_of_revaluation_id', 'reversalOfRevaluation', 'Reversal of Revaluation Account'),
                                        ]),
                                    ]),

                                Section::make('Tax Depreciation')
                                    ->description('Accounts for tax-specific depreciation differences.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            self::accountSelect('tax_depreciation_account_id', 'taxDepreciationAccount', 'Tax Depreciation Account'),
                                            self::accountSelect('deferred_tax_account_id', 'deferredTaxAccount', 'Deferred Tax Account'),
                                        ]),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    /**
     * Helper to create a standardised G/L Account selector.
     */
    protected static function accountSelect(string $name, string $relationship, string $label): FormSelect
    {
        return FormSelect::make($name)
            ->label($label)
            ->relationship($relationship, 'name')
            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->account_number} - {$record->name}")
            ->searchable()
            ->preload();
    }
}
