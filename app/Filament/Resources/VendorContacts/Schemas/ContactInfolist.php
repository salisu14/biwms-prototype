<?php

namespace App\Filament\Resources\VendorContacts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->weight('bold'),
                        TextEntry::make('company_name'),
                        TextEntry::make('type')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('role')
                            ->badge()
                            ->color(fn ($state) => $state?->color()),
                    ]),

                Section::make('Contact Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('email'),
                        TextEntry::make('phone'),
                        TextEntry::make('mobile'),
                    ]),

                Section::make('Address')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('address'),
                        TextEntry::make('city'),
                        TextEntry::make('state'),
                        TextEntry::make('postal_code'),
                        TextEntry::make('country'),
                    ]),
            ]);
    }
}
