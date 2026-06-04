<?php

namespace App\Filament\Resources\PurchaseInvoices\Tables;

use App\Enums\ApprovalStatus;
use App\Models\PurchaseInvoice;
use App\Services\Purchase\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class PurchaseInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->description(fn ($record) => $record->vendor?->vendor_code
                        ? "{$record->vendor->vendor_code} - ".($record->vendor?->vendor_name ?? $record->vendor_name ?? '')
                        : ''),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->is_overdue ? 'danger' : null),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record) => Number::currency((float) $state, $record->currency_code ?: config('app.default_currency', 'USD')))
                    ->sortable()
                    ->alignment('right'),
                SelectColumn::make('status')
                    ->options(ApprovalStatus::class)
                    ->disabled(fn ($record) => $record->isPosted()),
                TextColumn::make('remaining_amount')
                    ->formatStateUsing(fn ($state, $record) => Number::currency((float) $state, $record->currency_code ?: config('app.default_currency', 'USD')))
                    ->label('Balance')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->alignment('right'),
                IconColumn::make('paid_in_full')
                    ->boolean()
                    ->label('Paid'),
                IconColumn::make('cancelled')
                    ->boolean()
                    ->label('Cancelled')
                    ->trueColor('danger')
                    ->toggleable(),
                TextColumn::make('location.name')
                    ->label('Location')
                    ->description(fn ($record) => $record->location?->code ?? '')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('posted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ApprovalStatus::class),
                TernaryFilter::make('paid_in_full')
                    ->label('Paid Status'),
                TernaryFilter::make('cancelled'),
                SelectFilter::make('vendor_id')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable(),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record): bool => $record instanceof PurchaseInvoice && $record->status === ApprovalStatus::PENDING)
                    ->action(fn ($record) => $record instanceof PurchaseInvoice ? $record->update([
                        'status' => ApprovalStatus::APPROVED,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]) : null),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record instanceof PurchaseInvoice && $record->status === ApprovalStatus::PENDING)
                    ->action(fn ($record) => $record instanceof PurchaseInvoice ? $record->update([
                        'status' => ApprovalStatus::REJECTED,
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now(),
                    ]) : null),
                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record instanceof PurchaseInvoice && $record->status === ApprovalStatus::APPROVED)
                    ->action(function ($record, PurchaseInvoiceService $purchaseInvoiceService) {
                        if (! $record instanceof PurchaseInvoice) {
                            return;
                        }

                        $purchaseInvoiceService->post($record);
                    }),
                ViewAction::make()->visible(fn ($record): bool => method_exists($record, 'isPosted') ? ! $record->isPosted() : true),
                EditAction::make()->visible(fn ($record): bool => method_exists($record, 'isPosted') ? ! $record->isPosted() : false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
