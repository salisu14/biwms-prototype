<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkScheduleAssignments\Schemas;

use App\Models\Employee;
use App\Models\EmployeeShift;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeWorkScheduleAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Work Schedule Assignment')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('employee_id')
                            ->options(fn (): array => Employee::query()->orderBy('employee_number')->pluck('full_name', 'id')->all())
                            ->searchable()
                            ->required(),
                        Select::make('employee_shift_id')
                            ->label('Shift')
                            ->options(fn (): array => EmployeeShift::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        DatePicker::make('effective_from')->required(),
                        DatePicker::make('effective_until'),
                        CheckboxList::make('working_days')
                            ->options([
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                                7 => 'Sunday',
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                        Toggle::make('is_active')->default(true),
                    ]),
            ]);
    }
}
