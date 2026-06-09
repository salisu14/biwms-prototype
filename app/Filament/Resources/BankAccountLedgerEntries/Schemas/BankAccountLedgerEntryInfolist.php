<?php

namespace App\Filament\Resources\BankAccountLedgerEntries\Schemas;

use App\Enums\BankAccountLedgerEntryStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankAccountLedgerEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('entry_number')->badge()->color('primary'),
                                TextEntry::make('bankAccount.bank_name')->label('Bank Account'),
                                TextEntry::make('bank_account_no')->label('Account No.'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('posting_date')->date('d/m/Y'),
                                TextEntry::make('document_date')->date('d/m/Y')->placeholder('-'),
                                TextEntry::make('due_date')->date('d/m/Y')->placeholder('-'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('document_type')->placeholder('-'),
                                TextEntry::make('document_no'),
                                TextEntry::make('external_document_no')->placeholder('-'),
                            ]),
                        TextEntry::make('description')->columnSpanFull(),
                        TextEntry::make('description_2')->columnSpanFull()->placeholder('-'),
                    ])->columns(1),

                Section::make('Amounts')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('entry_type')->badge(),
                                TextEntry::make('amount')
                                    ->label('Amount')
                                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Number::currency(abs($state), 'NGN'))
                                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                                    ->weight('bold'),
                                TextEntry::make('currency_code')->label('Currency'),
                            ]),
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('debit_amount')->formatStateUsing(fn ($state) => \Illuminate\Support\Number::currency($state, 'NGN')),
                                TextEntry::make('credit_amount')->formatStateUsing(fn ($state) => \Illuminate\Support\Number::currency($state, 'NGN')),
                                TextEntry::make('amount_lcy')->label('Amount (LCY)')->formatStateUsing(fn ($state) => \Illuminate\Support\Number::currency($state, 'NGN')),
                                TextEntry::make('balance')->formatStateUsing(fn ($state) => \Illuminate\Support\Number::currency($state, 'NGN')),
                            ]),
                    ]),

                Section::make('Check Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('check_type')->badge()->placeholder('-'),
                                TextEntry::make('check_no')->placeholder('-'),
                                TextEntry::make('check_date')->date('d/m/Y')->placeholder('-'),
                            ]),
                    ])->visible(fn ($record) => $record->check_no !== null),

                Section::make('Reconciliation & Status')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('status')->badge()->color(fn (BankAccountLedgerEntryStatus $state) => $state->color()),
                                IconEntry::make('open')->boolean(),
                                TextEntry::make('statement_no')->placeholder('-'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('statement_line_no')->placeholder('-'),
                                TextEntry::make('reconciled_at')->dateTime('d/m/Y H:i')->placeholder('-'),
                                TextEntry::make('reconciledByUser.name')->label('Reconciled By')->placeholder('-'),
                            ]),
                    ]),

                Section::make('Void Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('voided_at')->dateTime('d/m/Y H:i')->placeholder('-'),
                                TextEntry::make('voidedByUser.name')->label('Voided By')->placeholder('-'),
                                TextEntry::make('void_reason')->placeholder('-')->columnSpan(2),
                            ]),
                    ])->visible(fn ($record) => $record->voided_at !== null),

                Section::make('Source & Ledger Links')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('glEntry.entry_number')->label('G/L Entry')->placeholder('-')->color('primary'),
                                TextEntry::make('vendorLedgerEntry.entry_number')->label('Vendor Entry')->placeholder('-'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('customerLedgerEntry.entry_number')->label('Customer Entry')->placeholder('-'),
                                TextEntry::make('transferEntry.entry_number')->label('Transfer Entry')->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}
