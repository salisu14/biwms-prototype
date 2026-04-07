<?php

namespace App\Filament\Resources\GeneralPostingSetups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneralPostingSetupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Posting Setup Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('generalBusinessPostingGroup.code')
                            ->label('Business Group')
                            ->weight('bold')
                            ->color('primary'),
                        TextEntry::make('generalProductPostingGroup.code')
                            ->label('Product Group')
                            ->weight('bold')
                            ->color('primary'),
                        IconEntry::make('blocked')
                            ->boolean()
                            ->label('Status (Blocked)'),
                    ]),

                Section::make('Account Mappings')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('salesAccount.name')->label('Sales Account'),
                        TextEntry::make('cogsAccount.name')->label('COGS Account'),
                        TextEntry::make('inventoryAccount.name')->label('Inventory Account'),
                        TextEntry::make('inventoryAdjAccount.name')->label('Inv. Adjustment Account'),
                        TextEntry::make('purchase_variance_account_id')
                            ->label('Purchase Variance')
                            ->placeholder('Not defined'),
                    ]),

                Section::make('Metadata')
                    ->columns(2)
                    ->compact()
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}
