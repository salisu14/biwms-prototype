<?php

namespace App\Filament\Resources\EmployeePayCodes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class EmployeePayCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('employee.employee_number')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('payCode.name')
                    ->label('Pay Code')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('amount')
                    ->label('Override Amount')
                    ->numeric(),
                \Filament\Tables\Columns\TextColumn::make('percentage')
                    ->label('Override %')
                    ->numeric(),
                \Filament\Tables\Columns\TextColumn::make('effective_date')
                    ->date()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
