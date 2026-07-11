<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendancePayrollReviewBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')->searchable()->sortable()->weight('bold'),
                TextColumn::make('period.code')->label('Attendance Period')->searchable(),
                TextColumn::make('payrollPeriod.start_date')->label('Payroll From')->date(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('total_overtime_minutes')->numeric()->sortable(),
                TextColumn::make('total_unpaid_minutes')->numeric()->sortable(),
                TextColumn::make('total_suggested_amount')->money()->sortable(),
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
