<?php

namespace App\Filament\Resources\FAPostingGroups\Schemas;

use App\Enums\IntangibleAssetType;
use App\Enums\LiquidityAssetType;
use App\Enums\TangibleAssetType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FAPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Identity')
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('description'),
                    ])->columns(2),

                Section::make('Financial Accounts')
                    ->schema([
                        Select::make('acquisition_cost_account_id')
                            ->relationship('acquisitionAccount', 'account_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('depreciation_account_id')
                            ->relationship('depreciationAccount', 'account_number')
                            ->searchable()
                            ->preload(),
                        Select::make('depreciation_expense_account_id')
                            ->relationship('depExpenseAccount', 'account_number')
                            ->searchable()
                            ->preload(),
                    ])->columns(3),

                Section::make('Disposal Accounts')
                    ->schema([
                        Select::make('disposal_proceeds_account_id')
                            ->relationship('disposalProceedsAccount', 'account_number')
                            ->label('Disposal Proceeds Account')
                            ->searchable(),
                        Select::make('gain_on_disposal_account_id')
                            ->relationship('gainOnDisposalAccount', 'account_number')
                            ->label('Gain on Disposal Account')
                            ->searchable(),
                        Select::make('loss_on_disposal_account_id')
                            ->relationship('lossOnDisposalAccount', 'account_number')
                            ->label('Loss on Disposal Account')
                            ->searchable(),
                    ])->columns(3),

                Section::make('Appreciation & Revaluation')
                    ->schema([
                        Select::make('appreciation_account_id')
                            ->relationship('appreciationAccount', 'account_number')
                            ->label('Appreciation Account')
                            ->searchable(),
                        Select::make('revaluation_gain_account_id')
                            ->relationship('revaluationGainAccount', 'account_number')
                            ->label('Revaluation Gain Account')
                            ->searchable(),
                    ])->columns(2),

                Section::make('Applicability')
                    ->schema([
                        CheckboxList::make('applicable_tangible_types')
                            ->options(TangibleAssetType::class),
                        CheckboxList::make('applicable_intangible_types')
                            ->options(IntangibleAssetType::class),
                        CheckboxList::make('applicable_liquidity_types')
                            ->options(LiquidityAssetType::class),
                    ])->columns(3),
            ]);
    }
}
