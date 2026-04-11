<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Main 2-Column Layout
                Grid::make(2)
                    ->schema([
                        // LEFT COLUMN: Identity & Counterparty
                        Section::make('General Information')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('payment_number')
                                        ->label('Payment No.')
                                        ->weight('bold')
                                        ->copyable(),

                                    TextEntry::make('payment_direction')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'RECEIPT' => 'success',
                                            'DISBURSEMENT' => 'danger',
                                            default => 'gray',
                                        }),

                                    TextEntry::make('payment_method')
                                        ->badge()
                                        ->color('gray'),
                                ]),

                                Grid::make(3)->schema([
                                    TextEntry::make('payment_date')
                                        ->label('Date')
                                        ->date(),

                                    TextEntry::make('posting_date')
                                        ->date(),

                                    TextEntry::make('clearing_date')
                                        ->label('Value/Clearing Date')
                                        ->date()
                                        ->placeholder('-'),
                                ]),
                            ]),

                        Section::make('Counterparty Details')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('party_name')
                                        ->label('Name')
                                        ->weight('medium'),

                                    TextEntry::make('party_type')
                                        ->badge(),
                                ]),

                                Section::make('Counterparty Bank Information')
                                    ->collapsible()
                                    ->collapsed()
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('counterparty_bank_name')
                                                ->label('Bank'),
                                            TextEntry::make('counterparty_account_number')
                                                ->label('Account No.')
                                                ->formatStateUsing(fn ($state) => $state ? '•••• '.substr($state, -4) : '-'),
                                            TextEntry::make('counterparty_routing_number')
                                                ->label('Routing No.'),
                                        ]),
                                    ]),
                            ]),

                        // RIGHT COLUMN: Financials & Posting
                        Section::make('Financial Summary')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('payment_amount')
                                        ->label('Total Document Amount')
                                        ->money(fn ($record) => $record->currency?->code ?? $record->currency_code)
                                        ->size('lg')
                                        ->weight('bold')
                                        ->color(fn ($record) => $record->payment_direction === 'RECEIPT' ? 'success' : 'primary'),

                                    TextEntry::make('payment_amount_lcy')
                                        ->label('Value (LCY)')
                                        ->money('NGN'), // LCY for this system is NGN
                                ]),

                                Grid::make(2)->schema([
                                    TextEntry::make('applied_amount')
                                        ->label('Applied to Docs')
                                        ->money(fn ($record) => $record->currency?->code ?? $record->currency_code),

                                    TextEntry::make('unapplied_amount')
                                        ->label('Remaining Balance')
                                        ->money(fn ($record) => $record->currency?->code ?? $record->currency_code)
                                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                                ]),

                                Grid::make(3)->schema([
                                    TextEntry::make('discount_taken')
                                        ->label('Discount Taken')
                                        ->money(fn ($record) => $record->currency?->code ?? $record->currency_code),

                                    TextEntry::make('transaction_fee')
                                        ->label('Bank Fees')
                                        ->money(fn ($record) => $record->currency?->code ?? $record->currency_code),

                                    TextEntry::make('currency_factor')
                                        ->label('Exch. Rate (Factor)')
                                        ->numeric(6)
                                        ->placeholder('1.000000'),
                                ]),
                            ]),

                        Section::make('Bank & Posting')
                            ->schema([
                                TextEntry::make('bankAccount.account_name')
                                    ->label('Bank Account')
                                    ->icon('heroicon-m-building-library')
                                    ->weight('medium'),

                                Grid::make(3)->schema([
                                    TextEntry::make('bank_account_number')
                                        ->label('Account No.')
                                        ->placeholder('-'),

                                    TextEntry::make('check_number')
                                        ->label('Check No.')
                                        ->placeholder('-'),

                                    TextEntry::make('generalBusinessPostingGroup.code')
                                        ->label('Bus. Posting Group')
                                        ->placeholder('-'),
                                ]),
                            ]),
                    ]),

                // BOTTOM SECTION: Status & Audit
                Section::make('Status & Audit')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'POSTED' => 'success',
                                    'VOIDED' => 'danger',
                                    'OPEN' => 'warning',
                                    default => 'gray',
                                }),

                            IconEntry::make('reconciled')
                                ->label('Reconciled')
                                ->boolean(),

                            TextEntry::make('reconciled_at')
                                ->label('Reconciled At')
                                ->dateTime()
                                ->placeholder('-'),

                            TextEntry::make('posted_at')
                                ->dateTime()
                                ->placeholder('-'),
                        ]),

                        // Voiding Information (Hidden unless voided)
                        Section::make('Voiding Details')
                            ->visible(fn ($record) => $record->status === 'VOIDED')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('voided_at')->dateTime(),
                                    TextEntry::make('voided_by.name')->label('Voided By'),
                                ]),
                                TextEntry::make('void_reason')
                                    ->columnSpanFull(),
                            ])->collapsed(),

                        // Memos
                        Grid::make(2)->schema([
                            TextEntry::make('memo')
                                ->label('Internal Memo')
                                ->columnSpanFull()
                                ->placeholder('-'),

                            TextEntry::make('internal_notes')
                                ->label('Private Notes')
                                ->columnSpanFull()
                                ->placeholder('-'),
                        ]),
                    ]),
            ]);
    }
}
