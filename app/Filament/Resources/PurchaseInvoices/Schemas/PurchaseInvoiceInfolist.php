<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Enums\ApprovalStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseInvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Changed Split::make to Grid::make(2)
                Grid::make(2)
                    ->schema([
                        // Left Column (General, Status, Cancellation)
                        Group::make([
                            Section::make('General Information')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('document_number')
                                            ->label('Invoice No.')
                                            ->weight('bold')
                                            ->copyable(),
                                        TextEntry::make('external_document_number')
                                            ->label('External Doc No.')
                                            ->placeholder('-'),
                                        TextEntry::make('vendor_name')
                                            ->label('Vendor'),
                                        TextEntry::make('vendor_address')
                                            ->placeholder('-'),
                                        TextEntry::make('order_number')
                                            ->label('Source Order')
                                            ->placeholder('-'),
                                        TextEntry::make('location.name')
                                            ->label('Location'),
                                    ]),
                                ]),

                            Section::make('Status & Audit')
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextEntry::make('status')
                                            ->badge()
                                            ->label('Approval')
                                            ->color(function ($state): string {
                                                $value = $state instanceof ApprovalStatus ? $state->value : (string) $state;

                                                return match ($value) {
                                                    'draft' => 'gray',
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'posted' => 'info',
                                                    default => 'gray',
                                                };
                                            }),
                                        IconEntry::make('paid_in_full')
                                            ->label('Paid')
                                            ->boolean(),
                                        IconEntry::make('cancelled')
                                            ->label('Cancelled')
                                            ->boolean()
                                            ->trueColor('danger'),
                                        TextEntry::make('payment_status')
                                            ->badge()
                                            ->label('Payment Status')
                                            ->color(fn (string $state): string => match ($state) {
                                                'PAID' => 'success',
                                                'OVERDUE' => 'danger',
                                                'CANCELLED' => 'gray',
                                                default => 'warning',
                                            }),
                                    ]),
                                    Grid::make(2)->schema([
                                        TextEntry::make('poster.name')
                                            ->label('Posted By'),
                                        TextEntry::make('posted_at')
                                            ->dateTime(),
                                    ]),
                                ]),

                            Section::make('Cancellation Details')
                                ->visible(fn ($record) => $record->cancelled)
                                ->schema([
                                    TextEntry::make('corrective_document_number')
                                        ->label('Credit Memo Ref')
                                        ->weight('bold')
                                        ->color('danger'),
                                    TextEntry::make('cancellation_reason')
                                        ->placeholder('No reason provided'),
                                    TextEntry::make('cancelled_at')
                                        ->dateTime(),
                                ]),
                        ]), // Removed ->grow() as Grid handles this

                        // Right Column (Financials, Dates)
                        Group::make([
                            Section::make('Financial Summary')
                                ->schema([
                                    TextEntry::make('grand_total')
                                        ->label('Total (Incl. VAT)')
                                        ->money(fn ($record) => $record->currency_code)
                                        ->size('lg')
                                        ->weight('bold'),
                                    TextEntry::make('total_vat')
                                        ->label('VAT Amount')
                                        ->money(fn ($record) => $record->currency_code),
                                    TextEntry::make('amount_paid')
                                        ->label('Paid to Date')
                                        ->money(fn ($record) => $record->currency_code)
                                        ->color('success'),
                                    TextEntry::make('remaining_amount')
                                        ->label('Balance Due')
                                        ->money(fn ($record) => $record->currency_code)
                                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                                        ->weight('bold'),
                                ]),

                            Section::make('Dates')
                                ->schema([
                                    TextEntry::make('posting_date')->date(),
                                    TextEntry::make('document_date')->date(),
                                    TextEntry::make('due_date')
                                        ->date()
                                        ->color(fn ($record) => $record->is_overdue ? 'danger' : null),
                                ]),
                        ]), // Removed ->columnSpan(1) as Grid::make(2) handles this
                    ]),
            ]);
    }
}
