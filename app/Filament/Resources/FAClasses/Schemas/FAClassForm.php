<?php

namespace App\Filament\Resources\FAClasses\Schemas;

use App\Enums\FixedAssetType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FAClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Class Identification')
                    ->description('Define the primary categorization for fixed assets.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Class Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., TANGIBLE'),

                        TextInput::make('name')
                            ->label('Class Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Tangible Assets'),

                        Select::make('fa_type')
                            ->label('Fixed Asset Type')
                            ->options(FixedAssetType::class)
                            ->required()
                            ->enum(FixedAssetType::class)
                            ->native(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Default Accounting')
                    ->description('Set the default posting behavior for assets in this class.')
                    ->schema([
                        Select::make('default_posting_group_id')
                            ->label('Default Posting Group')
                            ->relationship('defaultPostingGroup', 'code') // Use code for better recognition
                            ->searchable()
                            ->preload()
                            ->placeholder('Select a posting group'),
                    ]),
            ]);
    }
}
