<?php

namespace App\Filament\Resources\InventoryPostingGroups\Schemas;

use App\Models\InventoryPostingGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryPostingGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->schema([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('e.g., FINISHED')
                            ->hint('Unique identifier for this group.')
                            // Lock the field if the record already exists in the database
                            ->disabled(fn (?InventoryPostingGroup $record) => $record !== null)
                            // Ensure the value is still sent to the database during creation
                            ->dehydrated()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->helperText('The code cannot be changed once the Inventory Posting Group is created.'),

                        TextInput::make('description')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Finished Goods'),

                        Toggle::make('blocked')
                            ->label('Blocked')
                            ->helperText('If blocked, this group cannot be used for new transactions.')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }
}
