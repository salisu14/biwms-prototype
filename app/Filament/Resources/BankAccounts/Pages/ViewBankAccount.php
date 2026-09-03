<?php

namespace App\Filament\Resources\BankAccounts\Pages;

use App\Filament\Resources\BankAccounts\BankAccountResource;
use App\Models\BankAccount;
use App\Models\GeneralLedgerSetup;
use App\Services\BankAccountLedgerService;
use App\Support\Filament\PostingFailureNotifier;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewBankAccount extends ViewRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('openingBalance')
                ->label('Enter Opening Balance')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->visible(fn (BankAccount $record): bool => auth()->user()?->can('openingBalance', $record) === true
                    && ! $record->ledgerEntries()
                        ->where('document_type', 'OPENING_BALANCE')
                        ->where('source_type', 'BANK')
                        ->where('source_id', $record->id)
                        ->exists())
                ->schema([
                    DatePicker::make('posting_date')->required()->default(now()),
                    TextInput::make('amount')->numeric()->minValue(0.0001)->required(),
                    Placeholder::make('opening_balance_equity_account')
                        ->label('Opening Balance Equity')
                        ->content(function (): string {
                            $account = GeneralLedgerSetup::query()->first()?->openingBalanceEquityAccount;

                            return $account
                                ? $account->account_number.' - '.$account->name
                                : 'Not configured. Set this in GL Fiscal Setup.';
                        }),
                    TextInput::make('description')->required()->default('Bank opening balance'),
                    TextInput::make('external_document_no')->label('External Reference')->nullable(),
                ])
                ->action(function (BankAccount $record, array $data): void {
                    try {
                        app(BankAccountLedgerService::class)->postOpeningBalance(
                            bankAccount: $record,
                            amount: (string) $data['amount'],
                            offsetAccountId: null,
                            postingDate: $data['posting_date'],
                            description: (string) $data['description'],
                            externalDocumentNo: $data['external_document_no'] ?? null,
                            userId: auth()->id(),
                        );

                        Notification::make()->title('Bank opening balance posted')->success()->send();
                    } catch (Throwable $exception) {
                        PostingFailureNotifier::notify($exception, 'Opening balance was not posted', [
                            'bank_account_id' => $record->getKey(),
                        ]);
                    }
                }),
        ];
    }
}
