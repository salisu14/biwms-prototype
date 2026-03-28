<?php

namespace App\Filament\Resources\InventoryPostingSetups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InventoryPostingSetupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                TextInput::make('inventory_account')
                    ->required(),
                TextInput::make('inventory_adjmt_account')
                    ->required(),
                TextInput::make('invt_accrual_account'),
                TextInput::make('cogs_account')
                    ->required(),
                TextInput::make('direct_cost_applied_account'),
                TextInput::make('overhead_applied_account'),
                TextInput::make('purchase_variance_account'),
                TextInput::make('material_variance_account'),
                TextInput::make('capacity_variance_account'),
                TextInput::make('subcontracted_variance_account'),
                TextInput::make('cap_overhead_variance_account'),
                TextInput::make('mfg_overhead_variance_account'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
