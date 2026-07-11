<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewItems\Tables;

use App\Models\AttendanceReviewItem;
use App\Services\Hr\AttendanceExceptionReviewService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendanceReviewItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendance_date')->date()->sortable(),
                TextColumn::make('employee.employee_number')->label('Emp No.')->searchable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(['employee.first_name', 'employee.last_name']),
                TextColumn::make('issue_type')->badge()->sortable(),
                TextColumn::make('severity')->badge()->sortable(),
                TextColumn::make('review_status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('issue_type'),
                SelectFilter::make('review_status')
                    ->options([
                        AttendanceReviewItem::STATUS_PENDING => 'Pending',
                        AttendanceReviewItem::STATUS_RESOLVED => 'Resolved',
                        AttendanceReviewItem::STATUS_WAIVED => 'Waived',
                        AttendanceReviewItem::STATUS_ESCALATED => 'Escalated',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('resolve')
                    ->color('success')
                    ->form([Textarea::make('notes')])
                    ->visible(fn (AttendanceReviewItem $record): bool => auth()->user()?->can('resolve', $record) ?? false)
                    ->action(function (AttendanceReviewItem $record, array $data): void {
                        app(AttendanceExceptionReviewService::class)->resolve($record, auth()->user(), 'approved', $data['notes'] ?? null);
                        Notification::make()->success()->title('Exception resolved')->send();
                    }),
                Action::make('waive')
                    ->color('warning')
                    ->form([Textarea::make('notes')->required()])
                    ->visible(fn (AttendanceReviewItem $record): bool => auth()->user()?->can('waive', $record) ?? false)
                    ->action(function (AttendanceReviewItem $record, array $data): void {
                        app(AttendanceExceptionReviewService::class)->waive($record, auth()->user(), $data['notes']);
                        Notification::make()->success()->title('Exception waived')->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
