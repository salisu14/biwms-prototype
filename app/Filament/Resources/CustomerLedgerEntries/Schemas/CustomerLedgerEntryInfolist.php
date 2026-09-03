<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Schemas;

use App\Filament\Resources\CustomerLedgerEntries\CustomerLedgerEntryResource;
use App\Models\CompanyInformation;
use App\Models\CustomerLedgerEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class CustomerLedgerEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Entry Summary')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('entry_number')
                            ->label('Entry No.'),
                        TextEntry::make('customer.name')
                            ->label('Customer')
                            ->formatStateUsing(fn ($state, $record) => $record->customer
                                ? "{$record->customer->customer_number} - {$record->customer->name}"
                                : 'Unknown Customer'),
                        TextEntry::make('document_type')
                            ->badge(),
                    ]),
                ]),
            Section::make('Amounts')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('amount')
                            ->label('Amount (LCY)')
                            ->formatStateUsing(fn ($state, $record) => Number::currency((float) $state, self::baseCurrencyCode($record->business_id))),
                        TextEntry::make('debit_amount')
                            ->label('Debit (LCY)')
                            ->formatStateUsing(fn ($state, $record) => Number::currency((float) $state, self::baseCurrencyCode($record->business_id))),
                        TextEntry::make('credit_amount')
                            ->label('Credit (LCY)')
                            ->formatStateUsing(fn ($state, $record) => Number::currency((float) $state, self::baseCurrencyCode($record->business_id))),
                        TextEntry::make('remaining_amount')
                            ->label('Remaining (LCY)')
                            ->formatStateUsing(fn ($state, $record) => Number::currency((float) $state, self::baseCurrencyCode($record->business_id))),
                        TextEntry::make('original_debit_amount')
                            ->label('Original Debit (FCY)')
                            ->formatStateUsing(fn ($state, $record): string => Number::format((float) $state, 2).' '.($record->currency_code ?? config('app.default_currency', 'USD'))),
                        TextEntry::make('original_credit_amount')
                            ->label('Original Credit (FCY)')
                            ->formatStateUsing(fn ($state, $record): string => Number::format((float) $state, 2).' '.($record->currency_code ?? config('app.default_currency', 'USD'))),
                    ]),
                ]),
            Section::make('Dates & Reference')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('posting_date')
                            ->date(),
                        TextEntry::make('document_date')
                            ->date(),
                        TextEntry::make('due_date')
                            ->date(),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('document_number')
                            ->label('Document No.'),
                        TextEntry::make('external_document_number')
                            ->label('External Ref.'),
                    ]),
                ]),
            Section::make('Notes')
                ->schema([
                    Group::make([
                        TextEntry::make('description')
                            ->columnSpanFull(),
                        TextEntry::make('comment')
                            ->columnSpanFull(),
                    ]),
                ]),
            Section::make('Audit & Reversal')
                ->schema([
                    TextEntry::make('reversal_entry_number')
                        ->label('Reversal Entry')
                        ->visible(fn (CustomerLedgerEntry $record): bool => $record->reversed)
                        ->url(fn (CustomerLedgerEntry $record): ?string => filled($record->reversal_entry_number)
                            ? CustomerLedgerEntryResource::getUrl('view', [
                                'record' => CustomerLedgerEntry::query()
                                    ->where('customer_id', $record->customer_id)
                                    ->where('entry_number', $record->reversal_entry_number)
                                    ->first()?->id,
                            ])
                            : null),
                    TextEntry::make('reversed_at')->label('Reversed At')->dateTime()->visible(fn (CustomerLedgerEntry $record): bool => $record->reversed),
                    TextEntry::make('reverser.name')->label('Reversed By')->visible(fn (CustomerLedgerEntry $record): bool => $record->reversed)->placeholder('—'),
                ])
                ->columns(3)
                ->collapsed(),
        ]);
    }

    private static function baseCurrencyCode(?int $businessId): string
    {
        return (string) (CompanyInformation::query()
            ->where('business_id', $businessId ?: (int) session('active_business_id', 0))
            ->value('base_currency_code') ?: config('app.base_currency', 'NGN'));
    }
}
