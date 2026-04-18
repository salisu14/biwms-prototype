<?php

namespace App\Filament\Resources\InventoryPostingSetups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryPostingSetupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Setup Definition')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('location.name')
                            ->label('Location')
                            ->placeholder('Global/All Locations')
                            ->weight('bold')
                            ->icon('heroicon-m-map-pin'),

                        TextEntry::make('inventoryPostingGroup.code')
                            ->label('Inventory Posting Group')
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('Financial Mappings')
                    ->description('General Ledger integration details.')
                    ->schema([
                        Grid::make(3)->schema([
                            // FIXED: Replaced invalid description() with formatStateUsing()
                            TextEntry::make('inventoryAccount.account_number')
                                ->label('Inventory Account')
                                ->formatStateUsing(fn ($state, $record) => $state.($record->inventoryAccount?->name ? " – {$record->inventoryAccount->name}" : ''))
                                ->icon('heroicon-m-building-library'),

                            TextEntry::make('inventoryAccountInterim.account_number')
                                ->label('Interim Account')
                                ->formatStateUsing(fn ($state, $record) => $state.($record->inventoryAccountInterim?->name ? " – {$record->inventoryAccountInterim->name}" : ''))
                                ->placeholder('Not configured'),

                            TextEntry::make('wipAccount.account_number')
                                ->label('WIP Account')
                                ->formatStateUsing(fn ($state, $record) => $state.($record->wipAccount?->name ? " – {$record->wipAccount->name}" : ''))
                                ->icon('heroicon-m-cog'),
                        ]),
                    ]),

                Section::make('Audit Trail')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')
                                ->label('Setup Created')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->label('Last Update')
                                ->dateTime(),
                        ]),
                    ]),
            ]);
    }
}
