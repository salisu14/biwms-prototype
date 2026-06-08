<?php

namespace App\Filament\Resources\PettyCashVouchers\Tables;

use App\Enums\PettyCashTransactionType;
use App\Enums\PettyCashVoucherStatus;
use App\Models\GlEntry;
use App\Models\PettyCashVoucher;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

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
                    ->formatStateUsing(fn($record) => \Illuminate\Support\Number::currency($record->total_amount, $record->fund->currency ?? 'NGN'))
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
                        \Filament\Forms\Components\DatePicker::make('from')->native(false),
                        \Filament\Forms\Components\DatePicker::make('until')->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->recordActions([
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
                    ->visible(fn($record) => $record->canApprove())
                    ->color('success')
                    ->icon('heroicon-m-check-circle'),

                Action::make('reject')
                    ->schema([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
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
                    ->visible(fn($record) => $record->canApprove())
                    ->color('danger')
                    ->icon('heroicon-m-x-circle'),

                Action::make('post')
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                // 1. Deduct from fund
                                $record->fund->deduct($record->total_amount);

                                $numberSeriesService = app(\App\Services\NumberSeriesService::class);

                                // 2. Generate Transaction Number safely
                                $transactionNumber = $numberSeriesService->getNextNo('PC-TRANS');

                                if (empty($transactionNumber)) {
                                    // Fallback if number series isn't configured
                                    $transactionNumber = 'GLT-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                                }

                                // 3. Create a payment transaction on the fund
                                $record->fund->transactions()->create([
                                    'petty_cash_voucher_id' => $record->id,
                                    'transaction_number' => $transactionNumber,
                                    'date' => $record->date,
                                    'type' => PettyCashTransactionType::PAYMENT,
                                    'amount' => -$record->total_amount,
                                    'running_balance' => $record->fund->fresh()->current_balance,
                                    'description' => "Payment: {$record->purpose}",
                                ]);

                                // 4. Validate that the fund has a GL Account assigned
                                if (empty($record->fund->chart_of_account_id)) {
                                    throw new \RuntimeException("Petty Cash Fund '{$record->fund->name}' does not have a G/L Account assigned.");
                                }

                                // 5. Create CREDIT entry for the Petty Cash Fund's GL Account (Once per voucher)
                                $creditEntryNumber = $numberSeriesService->tryGetNextNo('GL-ENTRY');
                                if (empty($creditEntryNumber)) {
                                    $creditEntryNumber = 'GLE-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                                }

                                GlEntry::create([
                                    'entry_number' => $creditEntryNumber,
                                    'transaction_number' => $transactionNumber,
                                    'amount' => -$record->total_amount, // Net amount (Credit is negative)
                                    'posting_date' => $record->date,
                                    'document_type' => 'Petty Cash Voucher',
                                    'document_number' => $record->voucher_number,
                                    'document_date' => $record->date,
                                    'description' => 'Petty Cash Payment: ' . $record->purpose,
                                    'debit_amount' => 0,
                                    'credit_amount' => $record->total_amount, // CREDIT the cash account
                                    'chart_of_account_id' => $record->fund->chart_of_account_id, // CASH ACCOUNT
                                    'sourceable_id' => $record->id,
                                    'sourceable_type' => \App\Models\PettyCashVoucher::class,
                                ]);

                                // 6. Create DEBIT entries for each expense line
                                foreach ($record->lines as $line) {

                                    // Validate that the line has an Expense Account assigned
                                    if (empty($line->expense_account_id)) {
                                        throw new \RuntimeException("Voucher line '{$line->description}' does not have an Expense G/L Account assigned.");
                                    }

                                    $debitEntryNumber = $numberSeriesService->tryGetNextNo('GL-ENTRY');
                                    if (empty($debitEntryNumber)) {
                                        $debitEntryNumber = 'GLE-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                                    }

                                    GlEntry::create([
                                        'entry_number' => $debitEntryNumber,
                                        'transaction_number' => $transactionNumber,
                                        'amount' => $line->amount, // Net amount (Debit is positive)
                                        'posting_date' => $record->date,
                                        'document_type' => 'Petty Cash Voucher',
                                        'document_number' => $record->voucher_number,
                                        'document_date' => $record->date,
                                        'description' => $line->description,
                                        'debit_amount' => $line->amount, // DEBIT the expense account
                                        'credit_amount' => 0,
                                        'shortcut_dimension_1_code' => $line->dimension_department_id,
                                        'shortcut_dimension_2_code' => $line->dimension_project_id,
                                        'chart_of_account_id' => $line->expense_account_id, // EXPENSE ACCOUNT
                                        'sourceable_id' => $record->id,
                                        'sourceable_type' => \App\Models\PettyCashVoucher::class,
                                    ]);
                                }

                                // 7. Update voucher status
                                $record->update([
                                    'status' => PettyCashVoucherStatus::POSTED,
                                    'posted_by_id' => auth()->id(),
                                    'posted_at' => now(),
                                ]);
                            });
                        } catch (\RuntimeException $e) {
                            // Catch the Number Series or Validation errors and show a friendly notification
                            \Filament\Notifications\Notification::make()
                                ->title('Posting Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            // Halt further execution
                            return;
                        } catch (\Exception $e) {
                            // Catch any other unexpected database errors
                            \Filament\Notifications\Notification::make()
                                ->title('Posting Failed')
                                ->body('An unexpected error occurred. Please check the logs.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Only show success if the try block succeeds
                        \Filament\Notifications\Notification::make()
                            ->title('Voucher Posted')
                            ->body('The voucher has been successfully posted and transactions have been created.')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->canPost())
                    ->color('info')
                    ->icon('heroicon-m-arrow-up-on-square'),

                Action::make('cancel')
                    ->action(function ($record) {
                        $record->update([
                            'status' => PettyCashVoucherStatus::CANCELLED,
                        ]);
                    })
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->canCancel())
                    ->color('gray')
                    ->icon('heroicon-m-archive-box'),

                Action::make('print')
                    ->label('Print Voucher')
                    ->icon('heroicon-m-printer')
                    ->url(fn (PettyCashVoucher $record) => route('petty-cash.vouchers.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => in_array($record->status, [
                        PettyCashVoucherStatus::APPROVED,
                        PettyCashVoucherStatus::POSTED
                    ])),
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
