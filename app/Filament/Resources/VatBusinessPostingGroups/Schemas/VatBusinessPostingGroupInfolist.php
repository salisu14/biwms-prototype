<?php

namespace App\Filament\Resources\VatBusinessPostingGroups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VatBusinessPostingGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Information')
                    ->schema([
                        Grid::make(1)->schema([
                            TextEntry::make('code')
                                ->label('Business Posting Group Code'),
                            TextEntry::make('description'),
                        ]),
                    ]),
            ]);
    }
}
