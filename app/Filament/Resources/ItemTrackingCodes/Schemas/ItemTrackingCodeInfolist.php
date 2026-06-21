<?php

namespace App\Filament\Resources\ItemTrackingCodes\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemTrackingCodeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')->label('Code')->weight('bold')->color('primary'),
                        TextEntry::make('description'),
                    ]),

                Grid::make(3)->schema([
                    Section::make('Tracking Rules')
                        ->columnSpan(1)
                        ->schema([
                            IconEntry::make('snspecific_tracking')->label('SN Tracking')->boolean(),
                            IconEntry::make('lotspecific_tracking')->label('Lot Tracking')->boolean(),
                            IconEntry::make('lot_wholesale_tracking')->label('Lot Wholesale')->boolean(),
                        ]),

                    Section::make('Expiration Policy')
                        ->columnSpan(1)
                        ->schema([
                            IconEntry::make('strict_expiration_posting')->label('Strict Posting')->boolean(),
                            IconEntry::make('man_expiration_date_entry_reqd')->label('Manual Entry Req.')->boolean(),
                            IconEntry::make('allow_expiration_correction')->label('Allow Correction')->boolean(),
                        ]),

                    Section::make('Timestamps')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('created_at')->dateTime(),
                            TextEntry::make('updated_at')->dateTime(),
                        ]),
                ]),
            ]);
    }
}
