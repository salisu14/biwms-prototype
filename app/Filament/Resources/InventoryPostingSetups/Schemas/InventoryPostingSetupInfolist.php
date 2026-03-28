<?php

namespace App\Filament\Resources\InventoryPostingSetups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InventoryPostingSetupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('description'),
                TextEntry::make('inventory_account'),
                TextEntry::make('inventory_adjmt_account'),
                TextEntry::make('invt_accrual_account')
                    ->placeholder('-'),
                TextEntry::make('cogs_account'),
                TextEntry::make('direct_cost_applied_account')
                    ->placeholder('-'),
                TextEntry::make('overhead_applied_account')
                    ->placeholder('-'),
                TextEntry::make('purchase_variance_account')
                    ->placeholder('-'),
                TextEntry::make('material_variance_account')
                    ->placeholder('-'),
                TextEntry::make('capacity_variance_account')
                    ->placeholder('-'),
                TextEntry::make('subcontracted_variance_account')
                    ->placeholder('-'),
                TextEntry::make('cap_overhead_variance_account')
                    ->placeholder('-'),
                TextEntry::make('mfg_overhead_variance_account')
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
