<?php

namespace App\Filament\Resources\AttendanceLedgerEntries\Tables;

use App\Models\AttendanceLedgerEntry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AttendanceLedgerEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('attendance_date', 'desc')
            ->columns([
                TextColumn::make('attendance_date')
                    ->date('D, M j')
                    ->sortable(),
                TextColumn::make('employee.employee_number')
                    ->label('Emp No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('clock_in_at')
                    ->dateTime('h:i A')
                    ->label('In')
                    ->sortable(),
                TextColumn::make('clock_out_at')
                    ->dateTime('h:i A')
                    ->label('Out')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('worked_hours')
                    ->label('Hours')
                    ->formatStateUsing(fn ($state) => $state !== null ? "{$state} hrs" : '—')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'APPROVED' => 'success',
                        'REJECTED' => 'danger',
                        'OPEN' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // CRITICAL: Date range filter for HR
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')->default(now()->startOfMonth()),
                        DatePicker::make('until')->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('attendance_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('attendance_date', '<=', $date));
                    }),

                SelectFilter::make('status')
                    ->options([
                        'OPEN' => 'Open',
                        'APPROVED' => 'Approved',
                        'REJECTED' => 'Rejected',
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    // ANTI-FRAUD: Hide edit if clocked out OR approved
                    EditAction::make()
                        ->visible(fn (AttendanceLedgerEntry $record): bool =>
                            $record->status === 'OPEN' && !$record->clock_out_at
                        ),

                    Action::make('clock_out')
                        ->label('Clock Out')
                        ->icon('heroicon-o-clock')
                        ->color('gray')
                        ->visible(fn (AttendanceLedgerEntry $record): bool =>
                            $record->status === 'OPEN' && $record->clock_in_at && !$record->clock_out_at
                        )
                        ->action(function (AttendanceLedgerEntry $record): void {
                            $record->update(['clock_out_at' => now()]);
                            Notification::make()->success()->title('Clocked out successfully')->send();
                        }),

                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (AttendanceLedgerEntry $record): bool => $record->status === 'OPEN')
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
                            Notification::make()->success()->title('Entry Approved')->send();
                        }),

                    // IMPROVEMENT: Reject now requires a reason
                    Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Textarea::make('rejection_reason')
                                ->label('Reason for Rejection')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->visible(fn (AttendanceLedgerEntry $record): bool => $record->status === 'OPEN')
                        ->action(function (AttendanceLedgerEntry $record, array $data): void {
                            if (!auth()->user()?->can('hr.attendance.reject')) {
                                Notification::make()->danger()->title('Not allowed')->body('Missing permission: hr.attendance.reject')->send();
                                return;
                            }
                            $record->update([
                                'status' => 'REJECTED',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                                'approval_note' => $data['rejection_reason'],
                            ]);
                            Notification::make()->success()->title('Entry Rejected')->send();
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Bulk Approve
                    BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $count = $records->where('status', 'OPEN')->each(fn ($r) => $r->update([
                                'status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()
                            ]))->count();
                            Notification::make()->title("{$count} entries approved")->success()->send();
                        }),

                    // Bulk Reject
                    BulkAction::make('reject')
                        ->label('Reject Selected')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->form([
                            Textarea::make('rejection_reason')->label('Reason')->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $count = $records->where('status', 'OPEN')->each(fn ($r) => $r->update([
                                'status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now(), 'approval_note' => $data['rejection_reason']
                            ]))->count();
                            Notification::make()->title("{$count} entries rejected")->danger()->send();
                        }),

                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->can('hr.attendance.delete')),
                ]),
            ]);
    }
}
