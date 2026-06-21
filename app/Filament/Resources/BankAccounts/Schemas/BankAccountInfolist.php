<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankAccountInfolist
{
    /**
     * Configure the Infolist for the Bank Account resource
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(4)
                    ->schema([
                        // ROW 1: General & Detailed Identity
                        Section::make('General Information')
                            ->schema([
                                TextEntry::make('account_code')
                                    ->label('Code')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('account_name')
                                    ->label('Account Name'),
                                TextEntry::make('account_type')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('currency.code')
                                    ->label('Currency'),
                            ])
                            ->columnSpan(1),

                        Section::make('Account Details')
                            ->schema([
                                Grid::make(4)->schema([
                                    Group::make([
                                        TextEntry::make('account_number')
                                            ->label('Account No.')
                                            ->copyable()
                                            ->extraAttributes(['class' => 'font-mono']),
                                        TextEntry::make('bank_name')
                                            ->label('Institution'),
                                    ]),
                                    Group::make([
                                        TextEntry::make('bank_branch')
                                            ->label('Branch / Location'),
                                        TextEntry::make('swift_code')
                                            ->label('SWIFT / BIC')
                                            ->placeholder('-'),
                                    ]),
                                    TextEntry::make('iban')
                                        ->label('IBAN / Int. No.')
                                        ->placeholder('-')
                                        ->copyable()
                                        ->columnSpan(2),
                                ]),
                            ])
                            ->columnSpan(3),
                    ]),

                Grid::make(4)
                    ->schema([
                        Section::make('Current Valuation')
                            ->schema([
                                TextEntry::make('current_balance')
                                    ->label('Ledger Balance')
                                    ->money(fn($record) => $record->currency?->code)
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('primary'),
                                TextEntry::make('available_balance')
                                    ->label('Available')
                                    ->money(fn($record) => $record->currency?->code)
                                    ->color('success'),
                            ])
                            ->columnSpan(1),

                        Section::make('Accounting')
                            ->schema([
                                TextEntry::make('glAccount.name')
                                    ->label('G/L Account')
                                    ->icon('heroicon-m-book-open'),
                            ])
                            ->columnSpan(1),

                        Section::make('Status')
                            ->schema([
                                Grid::make(2)->schema([
                                    IconEntry::make('active')
                                        ->label('Active')
                                        ->boolean(),
                                    Group::make([
                                        IconEntry::make('allow_payments')
                                            ->label('AP')
                                            ->boolean(),
                                        IconEntry::make('allow_receipts')
                                            ->label('AR')
                                            ->boolean(),
                                    ])->columns(2),
                                ]),
                            ])
                            ->columnSpan(1),

                        Section::make('Reconciliation')
                            ->schema([
                                TextEntry::make('last_reconciliation_date')
                                    ->label('Last Recon')
                                    ->date(),
                                TextEntry::make('last_reconciliation_balance')
                                    ->label('Recon. Balance')
                                    ->money(fn($record) => $record->currency?->code),
                            ])
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
