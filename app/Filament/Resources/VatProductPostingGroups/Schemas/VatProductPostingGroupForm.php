<?php

namespace App\Filament\Resources\VatProductPostingGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VatProductPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Details')
                    ->description('Define the VAT Product Posting Group (e.g., Standard, Reduced, Zero).')
                    ->schema([
                        Grid::make(1)->schema([
                            TextInput::make('code')
                                ->label('Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g., STANDARD')
                                ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                            TextInput::make('description')
                                ->required()
                                ->placeholder('e.g., Standard Rate VAT Items'),
                        ]),
                    ]),
            ]);
    }
}
