<?php

namespace App\Filament\Resources\MachineCenters\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MachineCenterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('workCenter.name')
                    ->label('Work center'),
                TextEntry::make('capacity')
                    ->numeric(),
                TextEntry::make('efficiency')
                    ->numeric(),
                TextEntry::make('direct_unit_cost')
                    ->money(),
                TextEntry::make('indirect_cost_percent')
                    ->numeric(),
                TextEntry::make('overhead_rate')
                    ->numeric(),
                TextEntry::make('setup_time')
                    ->numeric(),
                TextEntry::make('wait_time')
                    ->numeric(),
                TextEntry::make('move_time')
                    ->numeric(),
                TextEntry::make('location_code')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
