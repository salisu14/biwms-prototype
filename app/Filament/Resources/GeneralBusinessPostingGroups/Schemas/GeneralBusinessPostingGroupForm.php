<?php

namespace App\Filament\Resources\GeneralBusinessPostingGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GeneralBusinessPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                TextInput::make('default_vat_bus_posting_group'),
                Toggle::make('auto_create_vat_bus_posting_group')
                    ->required(),
                Toggle::make('blocked')
                    ->required(),
            ]);
    }
}
