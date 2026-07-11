<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeShifts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shift')
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('code')->required()->maxLength(50),
                        TextInput::make('name')->required()->maxLength(255)->columnSpan(['md' => 2]),
                        TimePicker::make('start_time')->seconds(false)->required(),
                        TimePicker::make('end_time')->seconds(false)->required(),
                        Toggle::make('crosses_midnight'),
                        TextInput::make('break_minutes')->numeric()->minValue(0)->default(0),
                        TextInput::make('grace_minutes')->numeric()->minValue(0)->default(0),
                        TextInput::make('early_departure_grace_minutes')->numeric()->minValue(0)->default(0),
                        TextInput::make('overtime_threshold_minutes')->numeric()->minValue(0)->default(0),
                        Toggle::make('is_weekend'),
                        Toggle::make('is_active')->default(true),
                    ]),
            ]);
    }
}
