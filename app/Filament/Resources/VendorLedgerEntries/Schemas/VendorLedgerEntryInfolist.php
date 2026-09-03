<?php

namespace App\Filament\Resources\VendorLedgerEntries\Schemas;

use App\Filament\Resources\VendorLedgerEntries\VendorLedgerEntryResource;
use App\Models\CompanyInformation;
use App\Models\VendorLedgerEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

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
                        TextEntry::make('vendor.vendor_name')->label('Vendor'),
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
                            ->label('Debit (LCY)')
                            ->state(fn (VendorLedgerEntry $record): string => Number::currency((float) $record->debit_amount, self::baseCurrencyCode($record->business_id)))
                            ->color('danger'),
                        //                            ->visible(fn ($record) => $record->debit_amount > 0),

                        TextEntry::make('credit_amount')
                            ->label('Credit (LCY)')
                            ->state(fn (VendorLedgerEntry $record): string => Number::currency((float) $record->credit_amount, self::baseCurrencyCode($record->business_id)))
                            ->color('success'),
                        //                            ->visible(fn ($record) => $record->credit_amount > 0),

                        TextEntry::make('remaining_amount')
                            ->label('Remaining (LCY)')
                            ->state(fn (VendorLedgerEntry $record): string => Number::currency((float) $record->remaining_amount, self::baseCurrencyCode($record->business_id)))
                            ->weight('bold')
                            ->color(fn ($record) => $record->open ? 'warning' : 'success'),

                        TextEntry::make('original_debit_amount')
                            ->label('Original Debit (FCY)')
                            ->state(fn (VendorLedgerEntry $record): string => Number::format((float) $record->original_debit_amount, 2).' '.($record->currency_code ?? config('app.default_currency', 'USD'))),

                        TextEntry::make('original_credit_amount')
                            ->label('Original Credit (FCY)')
                            ->state(fn (VendorLedgerEntry $record): string => Number::format((float) $record->original_credit_amount, 2).' '.($record->currency_code ?? config('app.default_currency', 'USD'))),

                        IconEntry::make('open')->label('Open?')->boolean(),
                        IconEntry::make('fully_applied')->label('Fully Applied?')->boolean(),
                    ])->columns(3),

                Section::make('Aging & Discounts')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('aging_category')
                            ->label('Aging Bucket')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'OVER_90' => 'danger', '61-90' => 'warning', default => 'info'
                            })
                            ->state(fn ($record) => $record->aging_category),

                        TextEntry::make('days_overdue')
                            ->label('Days Overdue')
                            ->state(fn ($record) => $record->days_overdue ? $record->days_overdue.' days' : 'N/A')
                            ->color('danger'),

                        TextEntry::make('discount_available')
                            ->label('Discount Available (LCY)')
                            ->state(fn (VendorLedgerEntry $record): string => Number::currency((float) $record->discount_available, self::baseCurrencyCode($record->business_id)))
                            ->visible(fn (VendorLedgerEntry $record): bool => $record->discount_available > 0),

                        TextEntry::make('payment_discount_percent')->suffix('%')->placeholder('-'),
                        TextEntry::make('payment_discount_due_date')->date('d/m/Y')->placeholder('-'),
                    ])->columns(3),

                Section::make('Audit & Reversal')
                    ->icon('heroicon-o-shield-exclamation')
                    ->schema([
                        TextEntry::make('generalBusinessPostingGroup.code')->label('Gen. Bus. Group')->placeholder('-')->badge(),
                        TextEntry::make('vendorPostingGroup.code')->label('Vendor Group')->placeholder('-')->badge(),
                        IconEntry::make('reversed')->boolean()->visible(fn ($record) => $record->reversed),
                        TextEntry::make('reversal_entry_number')
                            ->label('Reversal Entry')
                            ->visible(fn (VendorLedgerEntry $record): bool => $record->reversed)
                            ->url(fn (VendorLedgerEntry $record): ?string => filled($record->reversal_entry_number)
                                ? VendorLedgerEntryResource::getUrl('view', [
                                    'record' => VendorLedgerEntry::query()
                                        ->where('entry_number', $record->reversal_entry_number)
                                        ->first()?->id,
                                ])
                                : null),
                        TextEntry::make('creator.name')->label('Created By')->placeholder('-'),
                        TextEntry::make('comment')->placeholder('-')->columnSpanFull(),
                    ])->columns(3)->collapsed(),
            ]);
    }

    private static function baseCurrencyCode(?int $businessId): string
    {
        return (string) (CompanyInformation::query()
            ->where('business_id', $businessId ?: (int) session('active_business_id', 0))
            ->value('base_currency_code') ?: config('app.base_currency', 'NGN'));
    }
}
