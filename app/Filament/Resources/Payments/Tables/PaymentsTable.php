<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Services\Finance\PaymentService;
use App\Services\Workflow\DocumentApprovalWorkflowService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_number')
                    ->label('No.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_direction')
                    ->label('Direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'RECEIPT' => 'success',
                        'DISBURSEMENT' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('party_name')
                    ->label('Counterparty')
                    ->searchable()
                    ->description(fn ($record) => $record->party_type),
                TextColumn::make('payment_amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency?->code ?? $record->currency_code)
                    ->sortable()
                    ->alignment('right'),
                TextColumn::make('unapplied_amount')
                    ->label('Balance')
                    ->money(fn ($record) => $record->currency?->code ?? $record->currency_code)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignment('right'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'POSTED' => 'success',
                        'VOIDED' => 'danger',
                        'APPROVED' => 'success',
                        'SUBMITTED' => 'warning',
                        'PENDING' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('reconciled')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('bankAccount.account_name')
                    ->label('Bank')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('payment_date', 'desc')
            ->filters([
                SelectFilter::make('payment_direction')
                    ->options([
                        'RECEIPT' => 'Receipts',
                        'DISBURSEMENT' => 'Disbursements',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'SUBMITTED' => 'Submitted',
                        'APPROVED' => 'Approved',
                        'POSTED' => 'Posted',
                        'VOIDED' => 'Voided',
                    ]),
                TernaryFilter::make('reconciled')
                    ->label('Reconciliation Status'),
                SelectFilter::make('party_type')
                    ->options([
                        'CUSTOMER' => 'Customers',
                        'VENDOR' => 'Vendors',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn ($record): bool => auth()->user()?->can('update', $record) === true && in_array($record->status, ['PENDING', 'SUBMITTED'], true)),
                    Action::make('submit')
                        ->label('Submit')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => auth()->user()?->can('submit', $record) === true && $record->status === 'PENDING')
                        ->action(function ($record, DocumentApprovalWorkflowService $workflow): void {
                            $workflow->submit($record, auth()->id());
                            Notification::make()->title('Payment Submitted')->success()->send();
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => auth()->user()?->can('approve', $record) === true && $record->status === 'SUBMITTED')
                        ->action(function ($record, DocumentApprovalWorkflowService $workflow): void {
                            $workflow->approve($record, auth()->id());
                            Notification::make()->title('Payment Approved')->success()->send();
                        }),
                    Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => auth()->user()?->can('reject', $record) === true && $record->status === 'SUBMITTED')
                        ->action(function ($record, DocumentApprovalWorkflowService $workflow): void {
                            $workflow->reject($record, auth()->id());
                            Notification::make()->title('Payment Rejected')->warning()->send();
                        }),
                    Action::make('reopen')
                        ->label('Reopen')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => auth()->user()?->can('reopen', $record) === true && in_array($record->status, ['SUBMITTED', 'APPROVED'], true))
                        ->action(function ($record, DocumentApprovalWorkflowService $workflow): void {
                            $workflow->reopen($record, auth()->id());
                            Notification::make()->title('Payment Reopened')->success()->send();
                        }),
                    Action::make('post')
                        ->label('Post')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => auth()->user()?->can('post', $record) ?? false)
                        ->disabled(fn ($record) => $record->status !== 'APPROVED')
                        ->action(function ($record, PaymentService $service): void {
                            $service->post($record, auth()->id());
                            Notification::make()->title('Payment Posted')->success()->send();
                        }),
                    Action::make('markReconciled')
                        ->label('Mark Reconciled')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => auth()->user()?->can('reconcile', $record) ?? false)
                        ->disabled(fn ($record) => $record->status !== 'POSTED'
                            || (bool) $record->reconciled
                            || empty($record->bank_account_id))
                        ->action(function ($record): void {
                            $record->update([
                                'reconciled' => true,
                                'status' => 'RECONCILED',
                                'reconciled_at' => now(),
                                'reconciled_by' => auth()->id(),
                            ]);
                            Notification::make()->title('Payment Reconciled')->success()->send();
                        }),
                    Action::make('undoReconciled')
                        ->label('Undo Reconciliation')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => auth()->user()?->can('reconcile', $record) ?? false)
                        ->disabled(fn ($record) => $record->status !== 'POSTED' || ! (bool) $record->reconciled)
                        ->action(function ($record): void {
                            $record->update([
                                'reconciled' => false,
                                'status' => 'POSTED',
                                'reconciled_at' => null,
                                'reconciled_by' => null,
                            ]);
                            Notification::make()->title('Reconciliation Reversed')->success()->send();
                        }),
                    Action::make('void')
                        ->label('Void')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => auth()->user()?->can('void', $record) ?? false)
                        ->form(SensitiveActionPasswordConfirmation::schemaWithPasswordConfirmation([
                            Textarea::make('reason')->required(),
                        ]))
                        ->disabled(fn ($record) => $record->status !== 'POSTED' || (bool) $record->reconciled)
                        ->action(function (array $data, $record, PaymentService $service): void {
                            $service->void($record, $data['reason'], auth()->id());
                            Notification::make()->title('Payment Voided')->success()->send();
                        }),
                ])->label('Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
