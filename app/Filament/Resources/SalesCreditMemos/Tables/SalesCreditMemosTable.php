<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesCreditMemos\Tables;

use App\Contracts\ApprovableStatus;
use App\Enums\ApprovalStatus;
use App\Models\SalesCreditMemo;
use App\Services\Approval\ApprovalService;
use App\Services\Sales\SalesCreditMemoService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

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

                Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (SalesCreditMemo $record) => $record->status instanceof ApprovableStatus && $record->status->canSubmitForApproval())
                    ->action(function (SalesCreditMemo $record) {
                        app(ApprovalService::class)->submitForApproval($record);
                        Notification::make()->title('Credit memo submitted for approval')->success()->send();
                    }),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (SalesCreditMemo $record): bool {
                        $entry = $record->currentApprovalEntry;

                        return $entry && ($entry->approver_id === Auth::id() || Auth::user()?->hasRole('super_admin'));
                    })
                    ->action(function (SalesCreditMemo $record) {
                        $entry = $record->currentApprovalEntry;
                        if ($entry) {
                            app(ApprovalService::class)->approve($entry);
                        }
                        Notification::make()->title('Credit memo approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (SalesCreditMemo $record): bool {
                        $entry = $record->currentApprovalEntry;

                        return $entry && ($entry->approver_id === Auth::id() || Auth::user()?->hasRole('super_admin'));
                    })
                    ->schema([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (SalesCreditMemo $record, array $data) {
                        $entry = $record->currentApprovalEntry;
                        if ($entry) {
                            app(ApprovalService::class)->reject($entry, $data['reason']);
                        }
                        Notification::make()->title('Credit memo rejected')->danger()->send();
                    }),

                Action::make('post')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SalesCreditMemo $record) => $record->status === ApprovalStatus::APPROVED)
                    ->action(function (SalesCreditMemo $record) {
                        app(SalesCreditMemoService::class)->post($record);
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

