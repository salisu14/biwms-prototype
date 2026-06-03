<?php

namespace App\Filament\Resources\VendorInvoices\Schemas;

use App\Models\VendorInvoice;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorInvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document & Vendor')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('document_number')->label('Invoice No.')->badge()->color('primary'),
                        TextEntry::make('vendor.name')->label('Vendor'),
                        TextEntry::make('vendor_invoice_no')->label('Vendor Inv. No.'),
                        TextEntry::make('document_type')->badge(),
                        TextEntry::make('source_document_no')->label('Source Doc')->placeholder('-'),
                        TextEntry::make('status')->badge()->color(fn ($state) => match($state) { 'OPEN' => 'gray', 'APPROVED' => 'info', 'POSTED' => 'success', 'PAID' => 'primary', default => 'warning' }),
                    ])->columns(3),

                Section::make('Financials')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        TextEntry::make('amount')->label('Subtotal')->money('NGN'),
                        TextEntry::make('discount_amount')->label('Discount')->money('NGN'),
                        TextEntry::make('tax_amount')->label('Tax')->money('NGN'),
                        TextEntry::make('amount_including_tax')->label('Total')->money('NGN')->weight('bold'),
                        TextEntry::make('remaining_amount')->label('Remaining')->money('NGN')->color('danger'),
                        TextEntry::make('payment_status')
                            ->label('Payment Status')
                            ->badge()
                            ->color(fn (VendorInvoice $record) => match($record->getPaymentStatus()) { 'PAID' => 'success', 'PARTIAL' => 'warning', 'UNPAID' => 'danger', default => 'gray' })
                            ->state(fn (VendorInvoice $record) => $record->getPaymentStatus()),
                        TextEntry::make('days_overdue')
                            ->label('Days Overdue')
                            ->state(fn (VendorInvoice $record) => $record->getDaysOverdue() ? $record->getDaysOverdue() . ' days' : 'N/A')
                            ->color(fn (VendorInvoice $record) => $record->getDaysOverdue() ? 'danger' : 'gray'),
                        TextEntry::make('currency_code')->badge(),
                        TextEntry::make('exchange_rate')->numeric(),
                    ])->columns(3),

                Section::make('Dates & Accounting')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        TextEntry::make('posting_date')->date('d/m/Y'),
                        TextEntry::make('vendor_invoice_date')->date('d/m/Y'),
                        TextEntry::make('due_date')->date('d/m/Y')->color(fn ($record) => $record->due_date < now() && $record->remaining_amount > 0 ? 'danger' : 'gray'),
                        TextEntry::make('receipt_date')->date('d/m/Y')->placeholder('-'),
                        TextEntry::make('payableAccount.name')->label('Payable Account')->placeholder('-'),
                        TextEntry::make('capExProject.name')->label('CapEx Project')->placeholder('-'),
                        IconEntry::make('capitalized')->boolean()->visible(fn ($record) => $record->capex_project_id !== null),
                    ])->columns(3),

                Section::make('Audit Trail')
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('requester.name')->label('Requested By')->placeholder('-'),
                        TextEntry::make('approver.name')->label('Approved By')->placeholder('-'),
                        TextEntry::make('approved_at')->dateTime()->placeholder('-'),
                        TextEntry::make('postedByUser.name')->label('Posted By')->placeholder('-'),
                        TextEntry::make('posted_at')->dateTime()->placeholder('-'),
                        TextEntry::make('description')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('internal_notes')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('created_at')->dateTime()->placeholder('-'),
                        TextEntry::make('updated_at')->dateTime()->placeholder('-'),
                        TextEntry::make('deleted_at')->dateTime()->visible(fn (VendorInvoice $record): bool => $record->trashed()),
                    ])->columns(3),
            ]);
    }
}
