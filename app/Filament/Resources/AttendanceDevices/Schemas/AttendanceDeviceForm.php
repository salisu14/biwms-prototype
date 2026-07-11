<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceDevices\Schemas;

use App\Models\AttendanceLocation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceDeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attendance Device')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('code')->required()->maxLength(50),
                        TextInput::make('name')->required()->maxLength(255),
                        Select::make('attendance_location_id')
                            ->label('Location')
                            ->options(fn (): array => AttendanceLocation::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable(),
                        Select::make('device_type')->options([
                            'web' => 'Web',
                            'kiosk' => 'Kiosk',
                            'mobile' => 'Mobile',
                            'biometric' => 'Biometric',
                        ])->default('web')->required(),
                        TextInput::make('serial_number')->maxLength(255),
                        Toggle::make('is_active')->default(true),
                    ]),
            ]);
    }
}
