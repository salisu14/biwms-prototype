<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeAttendanceDays\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeAttendanceDaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendance_date')->date()->sortable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('shift.name')->label('Shift')->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('first_clock_in_at')->label('In')->time()->toggleable(),
                TextColumn::make('last_clock_out_at')->label('Out')->time()->toggleable(),
                TextColumn::make('worked_minutes')->label('Worked')->formatStateUsing(fn ($state): string => number_format(((int) $state) / 60, 2).' h')->sortable(),
                TextColumn::make('late_minutes')->numeric()->sortable()->toggleable(),
                TextColumn::make('overtime_minutes')->numeric()->sortable()->toggleable(),
                IconColumn::make('payroll_review_required')->label('Payroll Review')->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'present' => 'Present',
                        'late' => 'Late',
                        'absent' => 'Absent',
                        'on_leave' => 'On Leave',
                        'holiday' => 'Holiday',
                        'weekend' => 'Weekend',
                        'missing_clock_out' => 'Missing Clock-out',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(false),
                ]),
            ]);
    }
}
