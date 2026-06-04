<?php

namespace App\Filament\Resources\VendorPostingGroups\Schemas;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VendorPostingGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('code')->label('Code'),
                        TextEntry::make('description')->label('Description'),
                        TextEntry::make('blocked')->badge()->boolean(),
                    ]),
                ]),
            Section::make('Account Mappings')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('payablesAccount.name')
                            ->label('Payables Account')
                            ->formatStateUsing(fn ($state, $record) => $record->payablesAccount
                                ? "{$record->payablesAccount->no} - {$record->payablesAccount->name}"
                                : 'Unknown Account'),
                        TextEntry::make('paymentDiscDebitAccount.name')
                            ->label('Discount Debit Account')
                            ->formatStateUsing(fn ($state, $record) => $record->paymentDiscDebitAccount
                                ? "{$record->paymentDiscDebitAccount->no} - {$record->paymentDiscDebitAccount->name}"
                                : '—'),
                        TextEntry::make('paymentDiscCreditAccount.name')
                            ->label('Discount Credit Account')
                            ->formatStateUsing(fn ($state, $record) => $record->paymentDiscCreditAccount
                                ? "{$record->paymentDiscCreditAccount->no} - {$record->paymentDiscCreditAccount->name}"
                                : '—'),
                        TextEntry::make('invoiceRoundingAccount.name')
                            ->label('Invoice Rounding Account')
                            ->formatStateUsing(fn ($state, $record) => $record->invoiceRoundingAccount
                                ? "{$record->invoiceRoundingAccount->no} - {$record->invoiceRoundingAccount->name}"
                                : '—'),
                    ]),
                ]),
        ]);
    }
}
