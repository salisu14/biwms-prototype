<?php

namespace App\Filament\Resources\MachineCenters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MachineCenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('work_center_id')
                    ->relationship('workCenter', 'name')
                    ->required(),
                TextInput::make('capacity')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('efficiency')
                    ->required()
                    ->numeric()
                    ->default(100),
                TextInput::make('direct_unit_cost')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('indirect_cost_percent')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('overhead_rate')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('setup_time')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('wait_time')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('move_time')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('location_code'),
            ]);
    }
}
