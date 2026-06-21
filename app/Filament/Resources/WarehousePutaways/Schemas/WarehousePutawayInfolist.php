<?php

namespace App\Filament\Resources\WarehousePutaways\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehousePutawayInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Put-away Header Details')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('no')
                                ->label('Document No.')
                                ->weight('bold')
                                ->copyable(),

                            TextEntry::make('status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Open' => 'gray',
                                    'In Progress' => 'warning',
                                    'Completed' => 'success',
                                    default => 'gray',
                                }),

                            TextEntry::make('location.name')
                                ->label('Warehouse Location')
                                ->icon('heroicon-m-map-pin')
                                ->color('primary'),
                        ]),
                    ]),

                Section::make('Logistics & Assignment')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('warehouseReceipt.document_number')
                                ->label('Originating Receipt')
                                ->placeholder('Direct / Internal'),

                            TextEntry::make('sorting_method')
                                ->label('Warehouse Path Optimization')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('assignedUser.name')
                                ->label('Handler / Picker')
                                ->placeholder('Unassigned')
                                ->icon('heroicon-m-user'),
                        ]),
                    ]),

                Section::make('System Audit')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')
                                ->label('Document Created')
                                ->dateTime(),

                            TextEntry::make('updated_at')
                                ->label('Last Modified')
                                ->dateTime(),
                        ]),
                    ]),
            ]);
    }
}
