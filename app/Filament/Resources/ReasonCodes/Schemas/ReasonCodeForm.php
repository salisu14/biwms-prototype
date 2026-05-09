<?php

namespace App\Filament\Resources\ReasonCodes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReasonCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('DAMAGE'),

                        TextInput::make('description')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Damaged Goods'),

                        Select::make('default_location_code')
                            ->relationship('location', 'code')
                            ->preload()
                            ->searchable(),

                        Select::make('default_bin_code')
                            ->relationship('bin', 'bin_code')
                            ->preload()
                            ->searchable(),

                        TextInput::make('inventory_adjustment_account')
                            ->label('Inventory Adjustment Account')
                            ->placeholder('6110')
                            ->helperText('G/L Account for posting adjustments'),

                        TextInput::make('inventory_account')
                            ->label('Inventory Account')
                            ->placeholder('2130')
                            ->helperText('G/L Account for inventory'),

                        Toggle::make('blocked')
                            ->label('Blocked')
                            ->helperText('Prevent use of this reason code'),

                        Textarea::make('comment')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
