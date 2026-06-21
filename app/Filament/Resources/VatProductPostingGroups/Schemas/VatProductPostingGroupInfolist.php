<?php

namespace App\Filament\Resources\VatProductPostingGroups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VatProductPostingGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Information')
                    ->schema([
                        Grid::make(1)->schema([
                            TextEntry::make('code')
                                ->label('Product Posting Group Code'),
                            TextEntry::make('description'),
                        ]),
                    ]),
            ]);
    }
}
