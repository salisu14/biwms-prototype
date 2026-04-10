<?php

namespace App\Filament\Resources\Employees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_number')
                    ->label('No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('job_title')
                    ->searchable(),
                TextColumn::make('assignment_type')
                    ->label('Assignment')
                    ->badge()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('business_code')
                    ->label('Business')
                    ->badge()
                    ->color('info'),
                TextColumn::make('factory_code')
                    ->label('Factory')
                    ->badge()
                    ->color('success'),
                TextColumn::make('department_code')
                    ->label('Department')
                    ->badge()
                    ->color('warning'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
