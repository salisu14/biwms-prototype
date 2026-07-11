<?php

declare(strict_types=1);

namespace App\Filament\Resources\OvertimeApprovals\Schemas;

use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OvertimeApprovalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overtime Approval')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('employee_id')
                            ->options(fn (): array => Employee::query()->orderBy('employee_number')->pluck('full_name', 'id')->all())
                            ->searchable()
                            ->required(),
                        DatePicker::make('attendance_date')->required(),
                        TextInput::make('requested_minutes')->numeric()->minValue(0)->required(),
                        TextInput::make('approved_minutes')->numeric()->minValue(0)->default(0),
                        Textarea::make('reason')->columnSpanFull(),
                    ]),
            ]);
    }
}
