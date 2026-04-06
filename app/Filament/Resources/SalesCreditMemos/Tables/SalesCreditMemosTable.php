<?php

namespace App\Filament\Resources\SalesCreditMemos\Tables;

use App\Enums\ApprovalStatus;
use App\Models\SalesCreditMemo;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesCreditMemosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('memo_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('effective_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('amount_including_vat')
                    ->label('Total')
                    ->money(fn ($record) => $record->currency_code ?? 'NGN')
                    ->alignment('right')
                    ->sortable(),

                TextColumn::make('posted_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ApprovalStatus::class),
                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn (SalesCreditMemo $record) => $record->isPosted()),

                Action::make('post')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SalesCreditMemo $record) => $record->status === ApprovalStatus::DRAFT)
                    ->action(function (SalesCreditMemo $record) {
                        $record->update([
                            'status' => ApprovalStatus::APPROVED,
                            'posted_at' => now(),
                            'posted_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Credit Memo Posted')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
