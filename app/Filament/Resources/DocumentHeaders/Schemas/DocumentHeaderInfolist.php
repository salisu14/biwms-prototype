<?php

namespace App\Filament\Resources\DocumentHeaders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DocumentHeaderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('doc_type'),
                TextEntry::make('doc_no'),
                TextEntry::make('doc_date')
                    ->date(),
                TextEntry::make('posting_date')
                    ->date(),
                TextEntry::make('status'),
                TextEntry::make('created_by')
                    ->numeric(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
