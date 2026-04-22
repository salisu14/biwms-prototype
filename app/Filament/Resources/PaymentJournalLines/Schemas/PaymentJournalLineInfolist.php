<?php

namespace App\Filament\Resources\PaymentJournalLines\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentJournalLineInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('vendor.name')
                            ->label('Vendor')
                            ->weight('bold'),

                        TextEntry::make('vendor_no')
                            ->label('Vendor No.'),

                        TextEntry::make('journalLine.posting_date')
                            ->label('Posting Date')
                            ->date(),

                        TextEntry::make('amount_paid')
                            ->label('Amount to Pay')
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

                        TextEntry::make('due_date')
                            ->label('Due Date')
                            ->date()
                            ->placeholder('—')
                            ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),
                    ]),

                Section::make('Application')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('applies_to_doc_type')
                            ->label('Applies-to Type')
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

                        IconEntry::make('payment_processed')
                            ->label('Processed')
                            ->boolean(),

                        IconEntry::make('exported_to_payment_jnl')
                            ->label('Exported')
                            ->boolean(),
                    ]),
            ]);
    }
}
