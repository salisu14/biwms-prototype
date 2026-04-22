<?php

namespace App\Filament\Resources\CashReceiptLines\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CashReceiptLineInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Receipt Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Customer')
                            ->weight('bold'),

                        TextEntry::make('customer_no')
                            ->label('Customer No.'),

                        TextEntry::make('journalLine.posting_date')
                            ->label('Posting Date')
                            ->date(),

                        TextEntry::make('amount_received')
                            ->label('Amount Received')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('$'),

                        TextEntry::make('applies_to_amount')
                            ->label('Applied Amount')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('$')
                            ->placeholder('—'),

                        TextEntry::make('remaining_amount')
                            ->label('Unapplied Amount')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('$')
                            ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                    ]),

                Section::make('Application')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('applies_to_doc_type')
                            ->label('Applies-to Doc. Type')
                            ->badge()
                            ->placeholder('On Account'),

                        TextEntry::make('applies_to_doc_no')
                            ->label('Applies-to Doc. No.')
                            ->placeholder('—'),
                    ]),

                Section::make('Payment Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('bankAccount.name')
                            ->label('Bank Account'),

                        TextEntry::make('payment_method_code')
                            ->label('Payment Method')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('check_no')
                            ->label('Check No.')
                            ->placeholder('—'),

                        TextEntry::make('check_date')
                            ->label('Check Date')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('journalLine.document_no')
                            ->label('Document No.'),

                        TextEntry::make('journalLine.source_code')
                            ->label('Source Code')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
