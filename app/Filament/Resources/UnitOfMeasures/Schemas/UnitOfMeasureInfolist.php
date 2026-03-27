<?php

namespace App\Filament\Resources\UnitOfMeasures\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UnitOfMeasureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('uom_code'),
                TextEntry::make('description'),
                TextEntry::make('conversion_factor')
                    ->numeric(),
                IconEntry::make('is_base_uom')
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
