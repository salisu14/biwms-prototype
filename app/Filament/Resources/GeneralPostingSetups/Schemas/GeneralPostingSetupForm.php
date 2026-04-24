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
                    ->description('Define posting group combination and status.')
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
                    ]),

                // UPDATED: Changed from Grid::make(2) to Grid::make(3) to fit Purchase Accounts
                Grid::make(3)->schema([
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

                    // ADDED: Purchase Accounts Section
                    Section::make('Purchase Accounts')
                        ->columnSpan(1)
                        ->compact()
                        ->schema([
                            Select::make('purchase_account_id')
                                ->label('Purchase Account')
                                ->relationship('purchaseAccount', 'name')
                                ->searchable()
                                ->preload()
                                ->required(), // Marked required to prevent the error you are seeing
                            Select::make('purchase_credit_memo_account_id')
                                ->label('Purch. Credit Memo Account')
                                ->relationship('purchaseCreditMemoAccount', 'name')
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
                            ->preload()
                            ->required(), // Often required for complete validation
                        Select::make('inventory_adj_account_id')
                            ->relationship('inventoryAdjAccount', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('direct_cost_applied_account_id')
                            ->relationship('directCostAppliedAccount', 'name')
                            ->searchable(),
                        Select::make('overhead_applied_account_id')
                            ->relationship('overheadAppliedAccount', 'name')
                            ->searchable(),
                        Select::make('purchase_variance_account_id')
                            ->relationship('purchaseVarianceAccount', 'name')
                            ->searchable(),
                        // Added the missing variance fields from your model
                        Select::make('material_variance_account_id')
                            ->relationship('materialVarianceAccount', 'name')
                            ->searchable(),
                        Select::make('capacity_variance_account_id')
                            ->relationship('capacityVarianceAccount', 'name')
                            ->searchable(),
                        Select::make('capacity_overhead_variance_account_id')
                            ->relationship('capacityOverheadVarianceAccount', 'name')
                            ->searchable(),
                        Select::make('manufacturing_overhead_variance_account_id')
                            ->relationship('manufacturingOverheadVarianceAccount', 'name')
                            ->searchable(),
                    ]),
            ]);
    }
}
