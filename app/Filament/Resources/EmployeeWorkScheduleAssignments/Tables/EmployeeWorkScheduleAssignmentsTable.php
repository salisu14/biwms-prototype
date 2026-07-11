<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkScheduleAssignments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeWorkScheduleAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('shift.name')->label('Shift')->sortable(),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_until')->date()->sortable()->placeholder('Open'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
