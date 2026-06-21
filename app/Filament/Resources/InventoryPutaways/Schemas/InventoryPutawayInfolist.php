<?php

namespace App\Filament\Resources\InventoryPutaways\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryPutawayInfolist
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
                                    'Pending' => 'warning',
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
                            TextEntry::make('source_document')
                                ->label('Originating Document'),

                            TextEntry::make('source_no')
                                ->label('Reference No.')
                                ->weight('bold')
                                ->copyable(),

                            TextEntry::make('assignedUser.name')
                                ->label('Handler / Picker')
                                ->placeholder('Unassigned')
                                ->icon('heroicon-m-user'),
                        ]),
                    ]),

                Section::make('System Audit')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('posting_date')
                                ->label('Posting Date')
                                ->dateTime()
                                ->placeholder('-'),

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
