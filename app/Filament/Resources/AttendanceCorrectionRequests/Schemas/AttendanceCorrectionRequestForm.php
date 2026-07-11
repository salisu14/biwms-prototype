<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceCorrectionRequests\Schemas;

use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceCorrectionRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Correction Request')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('employee_id')
                            ->options(fn (): array => Employee::query()->orderBy('employee_number')->pluck('full_name', 'id')->all())
                            ->searchable()
                            ->required(),
                        DatePicker::make('attendance_date')->required(),
                        DateTimePicker::make('requested_clock_in_at')->seconds(false),
                        DateTimePicker::make('requested_clock_out_at')->seconds(false),
                        Textarea::make('reason')->required()->columnSpanFull(),
                    ]),
            ]);
    }
}
