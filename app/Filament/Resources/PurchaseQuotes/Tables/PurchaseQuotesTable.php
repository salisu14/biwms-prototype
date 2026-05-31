<?php

namespace App\Filament\Resources\PurchaseQuotes\Tables;

use App\Enums\PurchaseQuoteStatus;
use App\Filament\Shared\Actions\ApprovalActions;
use App\Services\Approval\ApprovalService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PurchaseQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_no')
                    ->label('Document No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor.vendor_name') // Use name, not ID
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount_including_vat')
                    ->label('Total')
                    ->money(fn ($record) => $record->currency_code ?? 'USD')
                    ->sortable(),
                TextColumn::make('document_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('status')
                    ->options(PurchaseQuoteStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn ($record) => $record->status?->canSubmitForApproval() === true)
                    ->action(function ($record) {
                        app(ApprovalService::class)->submitForApproval($record);
                        Notification::make()->title('Submitted for approval')->success()->send();
                    }),
                ApprovalActions::makeCancelApprovalRequestAction(),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->approvalEntries()->where('status', 'created')
                        ->where(function ($q) {
                            $q->where('approver_id', Auth::id())->orWhere('delegated_to', Auth::id());
                        })
                        ->exists())
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $entry = $record->approvalEntries()->where('status', 'created')
                            ->where(function ($q) {
                                $q->where('approver_id', Auth::id())->orWhere('delegated_to', Auth::id());
                            })
                            ->orderBy('sequence_no')
                            ->first();

                        if (! $entry) {
                            Notification::make()->title('No pending approval')->danger()->send();

                            return;
                        }

                        app(ApprovalService::class)->approve($entry);
                        Notification::make()->title('Approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->approvalEntries()->where('status', 'created')
                        ->where(function ($q) {
                            $q->where('approver_id', Auth::id())->orWhere('delegated_to', Auth::id());
                        })
                        ->exists())
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $entry = $record->approvalEntries()->where('status', 'created')
                            ->where(function ($q) {
                                $q->where('approver_id', Auth::id())->orWhere('delegated_to', Auth::id());
                            })
                            ->orderBy('sequence_no')
                            ->first();

                        if (! $entry) {
                            Notification::make()->title('No pending approval')->danger()->send();

                            return;
                        }

                        app(ApprovalService::class)->reject($entry, $data['reason']);
                        Notification::make()->title('Rejected')->danger()->send();
                    }),
                ApprovalActions::makeDelegateAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
