<?php

namespace App\Filament\Resources\GeneralPostingSetups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneralPostingSetupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Configuration')
                    ->description('Define the posting group combination and status.')
                    ->columns(3)
                    ->schema([
                        Select::make('general_business_posting_group_id')
                            ->label('Bus. Posting Group')
                            ->relationship('generalBusinessPostingGroup', 'code')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('general_product_posting_group_id')
                            ->label('Prod. Posting Group')
                            ->relationship('generalProductPostingGroup', 'code')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Toggle::make('blocked')
                            ->label('Blocked')
                            ->inline(false),
                        //                            ->colors([
                        //                                'true' => 'danger',
                        //                                'false' => 'success',
                        //                            ]),
                    ]),

                Grid::make(2)->schema([
                    Section::make('Sales Accounts')
                        ->columnSpan(1)
                        ->compact()
                        ->schema([
                            Select::make('sales_account_id')
                                ->label('Sales Account')
                                ->relationship('salesAccount', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('sales_credit_memo_account_id')
                                ->label('Sales Credit Memo Account')
                                ->relationship('salesCreditMemoAccount', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('sales_prepayment_account_id')
                                ->label('Sales Prepayment Account')
                                ->relationship('salesPrepaymentAccount', 'name')
                                ->searchable()
                                ->preload(),
                        ]),

                    Section::make('COGS Accounts')
                        ->columnSpan(1)
                        ->compact()
                        ->schema([
                            Select::make('cogs_account_id')
                                ->label('COGS Account')
                                ->relationship('cogsAccount', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('cogs_credit_memo_account_id')
                                ->label('COGS Credit Memo Account')
                                ->relationship('cogsCreditMemoAccount', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('cogs_prepayment_account_id')
                                ->label('COGS Prepayment Account')
                                ->relationship('cogsPrepaymentAccount', 'name')
                                ->searchable()
                                ->preload(),
                        ]),
                ]),

                Section::make('Inventory & Manufacturing Accounts')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Select::make('inventory_account_id')
                            ->relationship('inventoryAccount', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('inventory_adj_account_id')
                            ->relationship('inventoryAdjAccount', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('direct_cost_applied_account_id')
                            ->relationship('directCostAppliedAccount', 'name')
                            ->searchable(),
                        Select::make('overhead_applied_account_id')
                            ->relationship('overheadAppliedAccount', 'name')
                            ->searchable(),
                        Select::make('purchase_variance_account_id')
                            ->relationship('purchaseVarianceAccount', 'name')
                            ->searchable(),
                    ]),
            ]);
    }
}
