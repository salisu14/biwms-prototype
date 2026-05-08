<?php

namespace App\Filament\Resources\CustomerPostingGroups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerPostingGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->weight('bold'),

                        TextEntry::make('description'),

                        IconEntry::make('blocked')
                            ->label('Blocked')
                            ->boolean(),
                    ]),

                Section::make('Financial Mapping')
                    ->description('General Ledger integration for customer receivables.')
                    ->schema([
                        TextEntry::make('receivablesAccount.account_number')
                            ->label('Receivables G/L Account')
                            ->formatStateUsing(fn ($state, $record) => "{$state} – {$record->receivablesAccount?->name}")
                            ->icon('heroicon-m-building-library')
                            ->weight('bold'),
                    ]),

                Section::make('Adjustments & Precision')
                    ->columns(2)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('payment_disc_debit_account_id')
                                ->label('Pmt. Disc. (Debit)')
                                ->placeholder('None configured'),

                            TextEntry::make('payment_disc_credit_account_id')
                                ->label('Pmt. Disc. (Credit)')
                                ->placeholder('None configured'),
                        ]),

                        Grid::make(3)->schema([
                            TextEntry::make('invoice_rounding_account_id')
                                ->label('Invoice Rounding'),

                            TextEntry::make('debit_rounding_account_id')
                                ->label('Debit Rounding'),

                            TextEntry::make('credit_rounding_account_id')
                                ->label('Credit Rounding'),
                        ]),
                    ]),

                Section::make('Audit Trail')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}
