<?php

namespace App\Filament\Resources\ValueEntries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ValueEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entry Information')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('entry_no'),
                            TextEntry::make('item.item_code')->label('Item No'),
                            TextEntry::make('posting_date')->date(),
                            TextEntry::make('location.code')->label('Location'),
                            TextEntry::make('description'),
                        ]),
                    ]),
                Section::make('Financials')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('cost_amount_actual')->money('NGN'),
                            TextEntry::make('unit_cost')->money('NGN'),
                            TextEntry::make('variance_amount')->money('NGN'),
                            IconEntry::make('gl_posted')->boolean(),
                        ]),
                    ]),
            ]);
    }
}
