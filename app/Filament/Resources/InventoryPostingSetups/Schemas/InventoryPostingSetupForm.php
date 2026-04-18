<?php

namespace App\Filament\Resources\InventoryPostingSetups\Schemas;

use App\Models\ChartOfAccount;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryPostingSetupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Setup Identification')
                    ->description('Define the combination of location and inventory posting group for this setup.')
                    ->columns(2)
                    ->schema([
                        Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Default (All Locations)')
                            ->helperText('Leave blank to apply these accounts to all locations for this group.'),

                        Select::make('inventory_posting_group_id')
                            ->label('Inventory Posting Group')
                            ->relationship('inventoryPostingGroup', 'code')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The category of items this setup applies to.'),
                    ]),

                Section::make('G/L Account Assignments')
                    ->description('Map the inventory transactions to specific accounts in the Chart of Accounts.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('inventory_account_id')
                                ->label('Inventory Account')
                                ->relationship('inventoryAccount', 'account_number')
                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('The balance sheet account for on-hand inventory.'),

                            Select::make('inventory_account_interim_id')
                                ->label('Inventory Account (Interim)')
                                ->relationship('inventoryAccountInterim', 'account_number')
                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                ->searchable()
                                ->preload()
                                ->helperText('Used for received but not yet invoiced transactions.'),

                            Select::make('wip_account_id')
                                ->label('WIP Account')
                                ->relationship('wipAccount', 'account_number')
                                ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->account_number} – {$record->name}")
                                ->searchable()
                                ->preload()
                                ->columnSpanFull()
                                ->helperText('Work-in-Process account used during manufacturing or assembly.'),
                        ]),
                    ]),
            ]);
    }
}
