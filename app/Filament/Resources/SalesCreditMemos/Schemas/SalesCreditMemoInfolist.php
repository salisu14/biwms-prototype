<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesCreditMemos\Schemas;

use App\Models\SalesCreditMemo;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                        TextEntry::make('original_invoice_number')
                            ->label('Original Invoice')
                            ->state(fn (SalesCreditMemo $record): ?string => $record->postedInvoice?->document_number ?? $record->invoice?->invoice_number)
                            ->placeholder('No linked invoice'),
                        TextEntry::make('net_amount')
                            ->label('Net')
                            ->state(fn (SalesCreditMemo $record): float => (float) $record->items()->sum('amount'))
                            ->money(fn (SalesCreditMemo $record): string => $record->currency_code ?? 'NGN'),
                        TextEntry::make('vat_total')
                            ->label('VAT')
                            ->state(fn (SalesCreditMemo $record): float => (float) $record->items()->sum('vat_amount'))
                            ->money(fn (SalesCreditMemo $record): string => $record->currency_code ?? 'NGN'),
                        TextEntry::make('total_amount')
                            ->label('Gross / Total')
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
                            ->columns(5),
                    ]),

                // Conditional Audit Trail Section
                Section::make('Audit Trail')
                    ->schema([
                        TextEntry::make('poster.name')
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
