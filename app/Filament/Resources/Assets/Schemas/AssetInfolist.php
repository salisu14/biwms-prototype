<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\AssetType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('General Information')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('asset_no')
                                    ->label('Asset Number')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('asset_type')
                                    ->badge()
                                    ->color(fn ($state) => match ($state) {
                                        AssetType::FIXED => 'info',
                                        AssetType::LIQUIDITY => 'success',
                                        default => 'gray',
                                    }),
                                TextEntry::make('description')
                                    ->columnSpanFull(),
                                TextEntry::make('description_2')
                                    ->label('Alternate Description')
                                    ->placeholder('-'),
                                TextEntry::make('search_name')
                                    ->label('Search Name')
                                    ->placeholder('-'),
                            ]),
                        ]),

                    Section::make('Classification Details')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('fixed_asset_category')
                                    ->label('FA Category')
                                    ->badge()
                                    ->visible(fn ($record) => $record->isFixedAsset()),
                                TextEntry::make('tangible_type')
                                    ->badge()
                                    ->visible(fn ($record) => $record->isTangible()),
                                TextEntry::make('intangible_type')
                                    ->badge()
                                    ->visible(fn ($record) => $record->isIntangible()),
                                TextEntry::make('liquidity_type')
                                    ->badge()
                                    ->visible(fn ($record) => $record->isLiquidityAsset()),
                                TextEntry::make('location.name')
                                    ->label('Location')
                                    ->placeholder('-'),
                            ]),
                        ]),

                    Section::make('Acquisition & Valuation')
                        ->visible(fn ($record) => $record->isFixedAsset())
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('acquisition_date')->date(),
                                TextEntry::make('acquisition_cost')->money(),
                                TextEntry::make('original_cost')->money(),
                                TextEntry::make('book_value')->label('Net Book Value')->money(),
                                TextEntry::make('salvage_value')->label('Residual Value')->money(),
                                TextEntry::make('accumulated_depreciation')->money(),
                            ]),
                        ]),

                    Section::make('Technical Specifications')
                        ->visible(fn ($record) => $record->isFixedAsset())
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('serial_no')->label('Serial #')->placeholder('-'),
                                TextEntry::make('registration_no')->label('Reg/VIN')->placeholder('-'),
                                TextEntry::make('acquisitionVendor.vendor_name')->label('Vendor Source'),
                                TextEntry::make('mainAsset.description')->label('Main Asset'),
                            ]),
                        ]),

                    Section::make('Liquidity & Banking')
                        ->visible(fn ($record) => $record->isLiquidityAsset())
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('opening_balance')->money(),
                                TextEntry::make('current_balance')->label('Current Balance')->money(),
                                TextEntry::make('last_reconciliation_date')->date(),
                                TextEntry::make('bankAccount.account_name')->label('Account Name'),
                                TextEntry::make('bank_account_no')->label('Account #'),
                                TextEntry::make('branch_code')->label('Branch'),
                                TextEntry::make('iban')->label('IBAN'),
                                TextEntry::make('swift_code')->label('SWIFT'),
                            ]),
                        ]),

                    Section::make('Entity Sub-ledger Links')
                        ->visible(fn ($record) => $record->isLiquidityAsset())
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('vendor.vendor_name')->label('Vendor'),
                                TextEntry::make('customer.name')->label('Customer'),
                                TextEntry::make('employee.id')->label('Employee ID'),
                                TextEntry::make('reference_document_no')->label('Ref Doc'),
                                TextEntry::make('expected_clearance_date')->date(),
                            ]),
                        ]),

                    Section::make('Notes')
                        ->collapsible()
                        ->schema([
                            TextEntry::make('notes')->markdown()->placeholder('No notes provided.'),
                        ]),
                ]),

                Group::make([
                    Section::make('System Summary')
                        ->schema([
                            TextEntry::make('net_book_value')
                                ->label(fn ($record) => $record->isFixedAsset() ? 'Current Book Value' : 'Ledger Balance')
                                ->state(fn ($record) => $record->getNetBookValue())
                                ->money()
                                ->size('lg')
                                ->weight('bold')
                                ->color('primary'),

                            TextEntry::make('currency.code')->label('Currency'),
                            TextEntry::make('active')
                                ->badge()
                                ->color(fn ($state) => $state ? 'success' : 'danger')
                                ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
                        ]),

                    Section::make('Depreciation Profile')
                        ->visible(fn ($record) => $record->isDepreciable())
                        ->schema([
                            TextEntry::make('depreciation_method')->badge(),
                            TextEntry::make('useful_life_months')->label('Useful Life')->suffix(' Months'),
                            TextEntry::make('depreciation_rate')->label('Annual Rate')->suffix('%'),
                            TextEntry::make('depreciation_start_date')->label('Starts')->date(),
                            TextEntry::make('last_depreciation_date')->label('Last Run')->date(),
                        ]),

                    Section::make('Audit Trail')
                        ->schema([
                            TextEntry::make('created_at')->label('Created')->dateTime()->size('sm'),
                            TextEntry::make('updated_at')->label('Last Modified')->dateTime()->size('sm'),
                        ]),

                    Section::make('Account Mapping')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            TextEntry::make('postingGroup.code')->label('Posting Group'),
                            TextEntry::make('assetAccount.account_number')->label('Asset G/L'),
                            TextEntry::make('accumDepAccount.account_number')->label('Accum. Depr. G/L'),
                            TextEntry::make('depExpenseAccount.account_number')->label('Depr. Expense G/L'),
                            TextEntry::make('gainLossAccount.account_number')->label('Gain/Loss G/L'),
                        ]),
                ]),
            ]);
    }
}
