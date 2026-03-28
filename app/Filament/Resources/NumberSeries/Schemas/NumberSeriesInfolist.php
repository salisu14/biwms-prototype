<?php

namespace App\Filament\Resources\NumberSeries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class NumberSeriesInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('description'),
                TextEntry::make('prefix'),
                TextEntry::make('starting_number')
                    ->numeric(),
                TextEntry::make('ending_number')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('current_number')
                    ->numeric(),
                TextEntry::make('year')
                    ->numeric(),
                IconEntry::make('is_active')
                    ->boolean(),
                IconEntry::make('allow_manual')
                    ->boolean(),
                TextEntry::make('module'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
