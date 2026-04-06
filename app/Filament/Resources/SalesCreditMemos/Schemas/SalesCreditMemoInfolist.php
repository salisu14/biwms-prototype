<?php

namespace App\Filament\Resources\SalesCreditMemos\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Group;

class SalesCreditMemoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header Information Section
                Section::make('General Information')
                    ->schema([
                        TextEntry::make('memo_number')
                            ->label('Memo Number')
                            ->weight('bold'),
                        TextEntry::make('customer.name')
                            ->label('Customer'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('effective_date')
                            ->date(),
                        TextEntry::make('invoice.invoice_number')
                            ->label('Original Invoice')
                            ->placeholder('No linked invoice'),
                        TextEntry::make('amount_including_vat')
                            ->label('Total Amount (Gross)')
                            ->money(fn ($record) => $record->currency_code ?? 'NGN'),
                    ])
                    ->columns(3),

                // Main Content: Items and Reason
                Section::make('Items & Reason')
                    ->schema([
                        TextEntry::make('reason')
                            ->columnSpanFull()
                            ->placeholder('No reason provided'),

                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('item.description')
                                    ->label('Item'),
                                TextEntry::make('quantity'),
                                TextEntry::make('unit_price')
                                    ->label('Unit Price')
                                    ->money(fn ($record) => $record->currency_code ?? 'NGN'),
                                TextEntry::make('vat_amount')
                                    ->label('VAT')
                                    ->money(fn ($record) => $record->currency_code ?? 'NGN'),
                                TextEntry::make('amount_including_vat')
                                    ->label('Line Total')
                                    ->money(fn ($record) => $record->currency_code ?? 'NGN'),
                            ])
                            ->columns(5)
                    ]),

                // Conditional Audit Trail Section
                Section::make('Audit Trail')
                    ->schema([
                        TextEntry::make('approver.name')
                            ->label('Posted By'),
                        TextEntry::make('posted_at')
                            ->label('Posted Date')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record && method_exists($record, 'isPosted') ? $record->isPosted() : false),
            ]);
    }
}
