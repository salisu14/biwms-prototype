<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeShifts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('start_time')->time(),
                TextColumn::make('end_time')->time(),
                TextColumn::make('break_minutes')->label('Break')->numeric()->toggleable(),
                TextColumn::make('grace_minutes')->label('Grace')->numeric()->toggleable(),
                IconColumn::make('crosses_midnight')->boolean()->toggleable(),
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
