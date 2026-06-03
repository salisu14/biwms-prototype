<?php

namespace App\Filament\Resources\VendorLedgerEntries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorLedgerEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document Details')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('entry_number')->label('Entry No.')->badge()->color('primary'),
                        TextEntry::make('vendor.name')->label('Vendor'),
                        TextEntry::make('document_type')->badge()->color('info')->formatStateUsing(fn ($state) => str_replace('_', ' ', $state)),
                        TextEntry::make('document_number')->label('Doc No.')->copyable(),
                        TextEntry::make('external_document_number')->label('Vendor Ref.')->placeholder('-'),
                        TextEntry::make('description')->columnSpanFull(),
                    ])->columns(3),

                Section::make('Financials & Status')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        TextEntry::make('posting_date')->date('d/m/Y'),
                        TextEntry::make('due_date')->date('d/m/Y')->placeholder('-'),
                        TextEntry::make('currency_code')->badge()->color('gray'),

                        TextEntry::make('debit_amount')
                            ->label('Debit')
                            ->money('NGN')
                            ->color('danger')
                            ->visible(fn ($record) => $record->debit_amount > 0),

                        TextEntry::make('credit_amount')
                            ->label('Credit')
                            ->money('NGN')
                            ->color('success')
                            ->visible(fn ($record) => $record->credit_amount > 0),

                        TextEntry::make('remaining_amount')
                            ->label('Remaining')
                            ->money('NGN')
                            ->weight('bold')
                            ->color(fn ($record) => $record->open ? 'warning' : 'success'),

                        IconEntry::make('open')->label('Open?')->boolean(),
                        IconEntry::make('fully_applied')->label('Fully Applied?')->boolean(),
                    ])->columns(3),

                Section::make('Aging & Discounts')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('aging_category')
                            ->label('Aging Bucket')
                            ->badge()
                            ->color(fn ($state) => match($state) { 'OVER_90' => 'danger', '61-90' => 'warning', default => 'info' })
                            ->state(fn ($record) => $record->aging_category),

                        TextEntry::make('days_overdue')
                            ->label('Days Overdue')
                            ->state(fn ($record) => $record->days_overdue ? $record->days_overdue . ' days' : 'N/A')
                            ->color('danger'),

                        TextEntry::make('discount_available')
                            ->label('Discount Available')
                            ->money('NGN')
                            ->state(fn ($record) => $record->discount_available)
                            ->visible(fn ($record) => $record->discount_available > 0),

                        TextEntry::make('payment_discount_percent')->suffix('%')->placeholder('-'),
                        TextEntry::make('payment_discount_due_date')->date('d/m/Y')->placeholder('-'),
                    ])->columns(3),

                Section::make('Audit & Reversal')
                    ->icon('heroicon-o-shield-exclamation')
                    ->schema([
                        TextEntry::make('generalBusinessPostingGroup.code')->label('Gen. Bus. Group')->placeholder('-')->badge(),
                        TextEntry::make('vendorPostingGroup.code')->label('Vendor Group')->placeholder('-')->badge(),
                        IconEntry::make('reversed')->boolean()->visible(fn ($record) => $record->reversed),
                        TextEntry::make('reversal_entry_number')->label('Reversal Entry')->visible(fn ($record) => $record->reversed)->url(fn ($record) => VendorLedgerEntryResource::getUrl('view', ['record' => VendorLedgerEntry::where('entry_number', $record->reversal_entry_number)->first()?->id])),
                        TextEntry::make('creator.name')->label('Created By')->placeholder('-'),
                        TextEntry::make('comment')->placeholder('-')->columnSpanFull(),
                    ])->columns(3)->collapsed(),
            ]);
    }
}
