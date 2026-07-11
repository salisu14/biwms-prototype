<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeAttendanceEvents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeAttendanceEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('attendance_date')->date()->sortable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('event_type')->badge()->sortable(),
                TextColumn::make('source')->badge()->toggleable(),
                TextColumn::make('location.name')->label('Location')->toggleable(),
                TextColumn::make('device.name')->label('Device')->toggleable(),
                TextColumn::make('verification_result')->badge()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->options([
                        'clock_in' => 'Clock In',
                        'clock_out' => 'Clock Out',
                        'correction_clock_in' => 'Correction Clock In',
                        'correction_clock_out' => 'Correction Clock Out',
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
