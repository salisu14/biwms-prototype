<?php

namespace App\Filament\Resources\InventoryAdjustmentJournals\Tables;

use App\Jobs\PostInventoryAdjustment;
use App\Services\Workflow\DocumentApprovalWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryAdjustmentJournalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('journal_batch_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Open', 'Submitted' => 'warning',
                        'Released' => 'primary',
                        'Posted' => 'success',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('location_code')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines'),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->getStateUsing(fn ($record) => $record->lines->sum('amount'))
                    ->money('USD')
                    ->toggleable(),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->toggleable(),

                TextColumn::make('posted_at')
                    ->dateTime()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Open' => 'Open',
                        'Submitted' => 'Submitted',
                        'Released' => 'Released',
                        'Posted' => 'Posted',
                        'Cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('location_code')
                    ->relationship('location', 'code'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => auth()->user()?->can('update', $record) === true && in_array($record->status, ['Open', 'Submitted'], true)),

                Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => auth()->user()?->can('submit', $record) === true && $record->status === 'Open')
                    ->action(function ($record, DocumentApprovalWorkflowService $workflow): void {
                        $workflow->submit($record, auth()->id());
                        Notification::make()->title('Journal submitted')->success()->send();
                    }),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => auth()->user()?->can('approve', $record) === true && $record->status === 'Submitted')
                    ->action(function ($record, DocumentApprovalWorkflowService $workflow): void {
                        $workflow->approve($record, auth()->id());
                        Notification::make()->title('Journal approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => auth()->user()?->can('reject', $record) === true && $record->status === 'Submitted')
                    ->action(function ($record, DocumentApprovalWorkflowService $workflow): void {
                        $workflow->reject($record, auth()->id());
                        Notification::make()->title('Journal rejected')->warning()->send();
                    }),

                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => auth()->user()?->can('reopen', $record) === true && in_array($record->status, ['Submitted', 'Released'], true))
                    ->action(function ($record, DocumentApprovalWorkflowService $workflow): void {
                        $workflow->reopen($record, auth()->id());
                        Notification::make()->title('Journal reopened')->success()->send();
                    }),

                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => auth()->user()?->can('post', $record) === true && $record->status === 'Released' && $record->canPost())
                    ->action(function ($record) {
                        // Dispatch to job/queue for posting logic
                        // This creates Item Ledger Entries, Value Entries, etc.
                        PostInventoryAdjustment::dispatch($record);
                        Notification::make()->title('Journal queued for posting')->success()->send();
                    }),

                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => false), // Prevent bulk delete - BC style,
                ]),
            ]);
    }
}
