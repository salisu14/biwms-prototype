<?php

namespace App\Filament\Resources\FAPostingGroups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FAPostingGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('description')
                    ->placeholder('-'),
                TextEntry::make('acquisition_cost_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('acquisition_cost_offset_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('depreciation_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('depreciation_expense_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('maintenance_expense_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('maintenance_cost_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('disposal_proceeds_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('gain_on_disposal_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('loss_on_disposal_account_id')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('appreciation_account_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('revaluation_gain_account_id')
                    ->numeric()
                    ->placeholder('-'),
            ]);
    }
}
