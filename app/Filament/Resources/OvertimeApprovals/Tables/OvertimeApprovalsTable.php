<?php

declare(strict_types=1);

namespace App\Filament\Resources\OvertimeApprovals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OvertimeApprovalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendance_date')->date()->sortable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('requested_minutes')->numeric()->sortable(),
                TextColumn::make('approved_minutes')->numeric()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
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
