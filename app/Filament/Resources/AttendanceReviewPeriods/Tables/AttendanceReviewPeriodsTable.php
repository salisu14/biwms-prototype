<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewPeriods\Tables;

use App\Models\AttendanceReviewPeriod;
use App\Services\Hr\AttendanceReviewPeriodService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceReviewPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->weight('bold'),
                TextColumn::make('date_from')->date()->sortable(),
                TextColumn::make('date_to')->date()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('items_count')->counts('items')->label('Exceptions')->badge(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (AttendanceReviewPeriod $record): bool => auth()->user()?->can('submit', $record) ?? false)
                    ->action(fn (AttendanceReviewPeriod $record) => self::notify(app(AttendanceReviewPeriodService::class)->submit($record, auth()->user()), 'Period submitted')),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (AttendanceReviewPeriod $record): bool => auth()->user()?->can('approve', $record) ?? false)
                    ->action(fn (AttendanceReviewPeriod $record) => self::notify(app(AttendanceReviewPeriodService::class)->approve($record, auth()->user()), 'Period approved')),
                Action::make('lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AttendanceReviewPeriod $record): bool => auth()->user()?->can('lock', $record) ?? false)
                    ->action(fn (AttendanceReviewPeriod $record) => self::notify(app(AttendanceReviewPeriodService::class)->lock($record, auth()->user()), 'Period locked')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function notify(AttendanceReviewPeriod $period, string $title): void
    {
        Notification::make()->success()->title($title)->body($period->code)->send();
    }
}
