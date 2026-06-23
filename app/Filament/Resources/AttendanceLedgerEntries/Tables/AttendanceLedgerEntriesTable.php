<?php

namespace App\Filament\Resources\AttendanceLedgerEntries\Tables;

use App\Models\AttendanceLedgerEntry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendanceLedgerEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendance_date')->date()->sortable(),
                TextColumn::make('employee.employee_number')->label('Employee No.')->searchable()->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('clock_in_at')->dateTime('H:i')->label('In'),
                TextColumn::make('clock_out_at')->dateTime('H:i')->label('Out'),
                TextColumn::make('worked_hours')->numeric(decimalPlaces: 2)->label('Hours'),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('approver.name')->label('Approved By')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'OPEN' => 'Open',
                        'APPROVED' => 'Approved',
                        'REJECTED' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('clock_out')
                        ->label('Clock Out')
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->visible(fn(AttendanceLedgerEntry $record): bool => $record->status === 'OPEN' && $record->clock_in_at && !$record->clock_out_at)
                        ->action(function (AttendanceLedgerEntry $record): void {
                            $record->update(['clock_out_at' => now()]);
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn(AttendanceLedgerEntry $record): bool => $record->status === 'OPEN')
                        ->action(function (AttendanceLedgerEntry $record): void {
                            if (!auth()->user()?->can('hr.attendance.approve')) {
                                Notification::make()->danger()->title('Not allowed')->body('Missing permission: hr.attendance.approve')->send();

                                return;
                            }

                            $record->update([
                                'status' => 'APPROVED',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);
                        }),
                    Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn(AttendanceLedgerEntry $record): bool => $record->status === 'OPEN')
                        ->action(function (AttendanceLedgerEntry $record): void {
                            if (!auth()->user()?->can('hr.attendance.reject')) {
                                Notification::make()->danger()->title('Not allowed')->body('Missing permission: hr.attendance.reject')->send();

                                return;
                            }

                            $record->update([
                                'status' => 'REJECTED',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
