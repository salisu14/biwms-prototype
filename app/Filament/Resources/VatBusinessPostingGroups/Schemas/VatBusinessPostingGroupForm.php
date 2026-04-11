<?php

namespace App\Filament\Resources\VatBusinessPostingGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VatBusinessPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Details')
                    ->description('Define the VAT Business Posting Group (e.g., Domestic, Export).')
                    ->schema([
                        Grid::make(1)->schema([
                            TextInput::make('code')
                                ->label('Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g., DOMESTIC')
                                ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                            TextInput::make('description')
                                ->required()
                                ->placeholder('e.g., Domestic Customers and Vendors'),
                        ]),
                    ]),
            ]);
    }
}
