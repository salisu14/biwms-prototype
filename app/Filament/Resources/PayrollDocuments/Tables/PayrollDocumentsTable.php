<?php

namespace App\Filament\Resources\PayrollDocuments\Tables;

use App\Enums\PayrollStatus;
use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use App\Filament\Shared\Actions\ApprovalActions;
use App\Models\PayrollDocument;
use App\Services\PayrollCalculationService;
use App\Services\PayrollPaymentService;
use App\Services\PayrollPostingService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PayrollDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period_start')
                    ->date()
                    ->sortable(),
                TextColumn::make('period_end')
                    ->date()
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Period')
                    ->formatStateUsing(fn (PayrollDocument $record) => $record->period
                        ? "{$record->period->start_date->format('Y-m-d')} to {$record->period->end_date->format('Y-m-d')}"
                        : '-'
                    )
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('total_earnings')
                    ->numeric()
                    ->money('NGN'),
                TextColumn::make('total_deductions')
                    ->numeric()
                    ->money('NGN'),
                TextColumn::make('total_net_pay')
                    ->numeric()
                    ->money('NGN'),
                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->url(fn (PayrollDocument $record): string => PayrollDocumentResource::getUrl('review', ['record' => $record])),
                Action::make('calculate')
                    ->label('Calculate')
                    ->icon('heroicon-o-cpu-chip')
                    ->color('info')
                    ->visible(fn (PayrollDocument $record) => (auth()->user()?->can('calculate', $record) ?? false) && $record->status === PayrollStatus::OPEN)
                    ->action(function (PayrollDocument $record) {
                        try {
                            app(PayrollCalculationService::class)->calculate($record);
                            Notification::make()->success()->title('Payroll Calculated!')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Calculation Failed')->body($e->getMessage())->send();
                        }
                    }),
                ApprovalActions::makeSendApprovalRequestAction(),
                ApprovalActions::makeCancelApprovalRequestAction(),
                ApprovalActions::makeApproveAction(),
                ApprovalActions::makeRejectAction(),
                ApprovalActions::makeDelegateAction(),
                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PayrollDocument $record) => (auth()->user()?->can('post', $record) ?? false) && $record->status === PayrollStatus::APPROVED)
                    ->action(function (PayrollDocument $record) {
                        try {
                            app(PayrollPostingService::class)->post($record);
                            Notification::make()->success()->title('Payroll Posted!')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Posting Failed')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('export_bank_file')
                    ->label('Bank File')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (PayrollDocument $record) => (auth()->user()?->can('pay', $record) ?? false) && $record->status === PayrollStatus::POSTED)
                    ->action(function (PayrollDocument $record) {
                        $csv = app(PayrollPaymentService::class)->generateBankFile($record);
                        $filename = "bank_payment_{$record->document_number}.csv";

                        return response()->streamDownload(function () use ($csv) {
                            echo $csv;
                        }, $filename);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
