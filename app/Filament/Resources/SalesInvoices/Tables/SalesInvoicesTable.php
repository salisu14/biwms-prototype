<?php

namespace App\Filament\Resources\SalesInvoices\Tables;

use App\Enums\ApprovalStatus;
use App\Models\SalesInvoice;
use App\Services\PostingService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\SelectColumn;
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
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->money(fn ($record) => $record->currency_code ?? 'USD')
                    ->alignment('right')
                    ->summarize(Sum::make()->label('Total Sales')),

                SelectColumn::make('status')
                    ->options(ApprovalStatus::class)
                    ->disabled(fn ($record) => $record->isPosted()),

                TextColumn::make('status')
                    ->badge()
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

                // ✅ Approve
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    // Ensure you compare against the Enum object if your model casts it
                    ->visible(fn ($record) => $record->status === ApprovalStatus::PENDING)
                    ->action(fn (SalesInvoice $record) => $record->update([
                        'status' => ApprovalStatus::APPROVED,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ])
                    ),

                // ❌ Reject
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === ApprovalStatus::PENDING)
                    ->action(fn (SalesInvoice $record) => $record->update([
                        'status' => ApprovalStatus::REJECTED,
                    ])
                    ),

                // 🚀 Post (only after approval)
                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === ApprovalStatus::APPROVED)
                    ->action(function (SalesInvoice $record) {
                        app(PostingService::class)
                            ->postSalesInvoice($record);
                    }),

                ViewAction::make()
                    ->visible(fn ($record) => ! $record->isPosted()),

                EditAction::make()
                    ->visible(fn ($record) => ! $record->isPosted()),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
