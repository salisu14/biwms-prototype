<?php

namespace App\Filament\Resources\WorkCenterGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkCenterGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Identification')
                    ->description('Define the primary classification for production capacity.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Group Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g., ASSEMBLY_DEPT')
                                ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                            TextInput::make('name')
                                ->label('Group Name')
                                ->required()
                                ->placeholder('e.g., Assembly & Finishing'),
                        ]),
                    ]),
            ]);
    }
}
