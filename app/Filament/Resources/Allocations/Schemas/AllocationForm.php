<?php

namespace App\Filament\Resources\Allocations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AllocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Allocation Rule Header')
                    ->description('Define the primary identification and total distribution for this rule.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Rule Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., CORP-OVERHEAD')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                        TextInput::make('total_percentage')
                            ->label('Total Distribution (%)')
                            ->numeric()
                            ->default(100)
                            ->required()
                            ->suffix('%')
                            ->readOnly()
                            ->helperText('Calculated based on associated distribution lines.'),

                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(255)
                            ->placeholder('e.g., Corporate Overhead Allocation Rule')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
