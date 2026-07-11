<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceCorrectionRequests\Tables;

use App\Models\AttendanceCorrectionRequest;
use App\Services\Hr\AttendanceCalculationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendanceCorrectionRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendance_date')->date()->sortable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('requested_clock_in_at')->label('Requested In')->dateTime()->toggleable(),
                TextColumn::make('requested_clock_out_at')->label('Requested Out')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        AttendanceCorrectionRequest::STATUS_SUBMITTED => 'Submitted',
                        AttendanceCorrectionRequest::STATUS_APPROVED => 'Approved',
                        AttendanceCorrectionRequest::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (AttendanceCorrectionRequest $record): bool => auth()->user()?->can('approve', $record) === true)
                    ->action(fn (AttendanceCorrectionRequest $record): mixed => app(AttendanceCalculationService::class)->approveCorrection($record, auth()->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
