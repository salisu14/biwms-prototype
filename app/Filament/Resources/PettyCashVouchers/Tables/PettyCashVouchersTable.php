<?php

namespace App\Filament\Resources\PettyCashVouchers\Tables;

use App\Enums\PettyCashVoucherStatus;
use App\Models\PettyCashVoucher;
use App\Services\PettyCashPostingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Illuminate\Support\Number;

class PettyCashVouchersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher_number')
                    ->label('Voucher #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('fund.name')
                    ->label('Fund')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payee_name')
                    ->label('Payee')
                    ->searchable()
                    ->limit(25),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn($record) => Number::currency($record->total_amount, $record->fund->currency ?? 'NGN'))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(PettyCashVoucherStatus $state): string => $state->color()),

                TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('posted_at')
                    ->label('Posted At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PettyCashVoucherStatus::class),

                SelectFilter::make('petty_cash_fund_id')
                    ->label('Fund')
                    ->relationship('fund', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn($record) => $record->status === PettyCashVoucherStatus::PENDING),

                    Action::make('approve')
                        ->action(function ($record) {
                            $record->update([
                                'status' => PettyCashVoucherStatus::APPROVED,
                                'approved_by_id' => auth()->id(),
                            ]);
                        })
                        ->requiresConfirmation()
                        ->visible(fn($record) => $record->canApprove() && (auth()->user()?->can('approve', $record) ?? false))
                        ->color('success')
                        ->icon('heroicon-m-check-circle'),

                    Action::make('reject')
                        ->schema([
                            Textarea::make('rejection_reason')
                                ->required()
                                ->maxLength(1000),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'status' => PettyCashVoucherStatus::REJECTED,
                                'rejection_reason' => $data['rejection_reason'],
                            ]);
                        })
                        ->requiresConfirmation()
                        ->visible(fn($record) => $record->canApprove() && (auth()->user()?->can('approve', $record) ?? false))
                        ->color('danger')
                        ->icon('heroicon-m-x-circle'),

                    Action::make('post')
                        ->action(function ($record, PettyCashPostingService $postingService) {
                            try {
                                $postingService->postVoucher($record, (int)auth()->id());
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title('Posting Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Posting Failed')
                                    ->body('An unexpected error occurred. Please check the logs.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            // Only show success if the try block succeeds
                            Notification::make()
                                ->title('Voucher Posted')
                                ->body('The voucher has been successfully posted and transactions have been created.')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->visible(fn($record) => $record->canPost() && (auth()->user()?->can('post', $record) ?? false))
                        ->color('info')
                        ->icon('heroicon-m-arrow-up-on-square'),

                    Action::make('cancel')
                        ->action(function ($record) {
                            $record->update([
                                'status' => PettyCashVoucherStatus::CANCELLED,
                            ]);
                        })
                        ->requiresConfirmation()
                        ->visible(fn($record) => $record->canCancel() && (auth()->user()?->can('cancel', $record) ?? false))
                        ->color('gray')
                        ->icon('heroicon-m-archive-box'),

                    Action::make('print')
                        ->label('Print Voucher')
                        ->icon('heroicon-m-printer')
                        ->url(fn(PettyCashVoucher $record) => route('petty-cash.vouchers.print', $record))
                        ->openUrlInNewTab()
                        ->visible(fn($record) => in_array($record->status, [
                            PettyCashVoucherStatus::APPROVED,
                            PettyCashVoucherStatus::POSTED,
                        ])),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn($records) => $records->every(fn($r) => $r->status === PettyCashVoucherStatus::PENDING)),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }
}
