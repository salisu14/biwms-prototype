<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceLocations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attendance Location')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('code')->required()->maxLength(50),
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('timezone')->required()->default('Africa/Lagos')->maxLength(80),
                        TextInput::make('allowed_radius_meters')->numeric()->minValue(0),
                        TextInput::make('latitude')->numeric(),
                        TextInput::make('longitude')->numeric(),
                        TextInput::make('address')->maxLength(255)->columnSpanFull(),
                        Toggle::make('is_active')->default(true),
                    ]),
            ]);
    }
}
