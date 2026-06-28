<?php

namespace App\Filament\Resources\SalesInvoices\Tables;

use App\Enums\ApprovalStatus;
use App\Models\SalesInvoice;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Workflow\DocumentApprovalWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class SalesInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, $record): string => $record->invoice_number
                        ? "{$record->invoice_number} - ".($record->customer?->name ?? '—')
                        : '—')
                    ->description(fn ($record): string => $record->customer?->customer_number ?? ''),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): string => $record->customer?->customer_number ?? ''),

                TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->money(fn ($record) => $record->currency_code ?? 'USD')
                    ->alignment('right')
                    ->summarize(Sum::make()->label('Total Sales')),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? (string) $state)
                    ->color(fn (ApprovalStatus $state): string => match ($state) {
                        ApprovalStatus::DRAFT => 'gray',
                        ApprovalStatus::PENDING => 'warning',
                        ApprovalStatus::APPROVED => 'success',
                        ApprovalStatus::REJECTED => 'danger',
                        ApprovalStatus::POSTED => 'info',
                        ApprovalStatus::CANCELLED => 'slate',
                        default => 'gray',
                    }),

                TextColumn::make('due_date')
                    ->date()
                    ->color(fn ($record) => $record->due_date->isPast() && ! $record->isPosted() ? 'danger' : null)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ApprovalStatus::class),
                Filter::make('overdue')
                    ->query(fn (Builder $query) => $query->where('due_date', '<', now())->where('status', '!=', 'posted')),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'posted' => 'Posted',
                    ]),

                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable(),

                Filter::make('date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('posting_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('posting_date', '<=', $data['until']));
                    }),
            ])
            ->recordActions([
                Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record instanceof SalesInvoice && auth()->user()?->can('submit', $record) === true && $record->status === ApprovalStatus::DRAFT)
                    ->action(function (SalesInvoice $record, DocumentApprovalWorkflowService $workflow): void {
                        $workflow->submit($record, auth()->id());
                        Notification::make()->title('Invoice submitted')->success()->send();
                    }),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record instanceof SalesInvoice && auth()->user()?->can('approve', $record) === true && $record->status === ApprovalStatus::PENDING)
                    ->action(function (SalesInvoice $record, DocumentApprovalWorkflowService $workflow): void {
                        $workflow->approve($record, auth()->id());
                        Notification::make()->title('Invoice approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record instanceof SalesInvoice && auth()->user()?->can('reject', $record) === true && $record->status === ApprovalStatus::PENDING)
                    ->action(function (SalesInvoice $record, DocumentApprovalWorkflowService $workflow): void {
                        $workflow->reject($record, auth()->id());
                        Notification::make()->title('Invoice rejected')->warning()->send();
                    }),

                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record instanceof SalesInvoice
                        && auth()->user()?->can('reopen', $record) === true
                        && in_array($record->status, [ApprovalStatus::PENDING, ApprovalStatus::APPROVED, ApprovalStatus::REJECTED], true))
                    ->action(function (SalesInvoice $record, DocumentApprovalWorkflowService $workflow): void {
                        $workflow->reopen($record, auth()->id());
                        Notification::make()->title('Invoice reopened')->success()->send();
                    }),

                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record instanceof SalesInvoice && auth()->user()?->can('post', $record) === true && $record->status === ApprovalStatus::APPROVED)
                    ->action(function (SalesInvoice $record) {
                        app(SalesInvoiceService::class)->post($record);
                        Notification::make()->title('Invoice posted')->success()->send();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record instanceof SalesInvoice
                        && auth()->user()?->can('cancel', $record) === true
                        && ! $record->isPosted()
                        && $record->status !== ApprovalStatus::CANCELLED)
                    ->action(function (SalesInvoice $record, DocumentApprovalWorkflowService $workflow): void {
                        $workflow->cancel($record, auth()->id());
                        Notification::make()->title('Invoice cancelled')->success()->send();
                    }),

                ViewAction::make()
                    ->visible(fn ($record): bool => $record instanceof SalesInvoice && auth()->user()?->can('view', $record) === true),

                EditAction::make()
                    ->visible(fn ($record): bool => $record instanceof SalesInvoice && auth()->user()?->can('update', $record) === true),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
