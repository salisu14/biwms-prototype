<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    Section::make('Profile Summary')
                        ->columnSpan(2)
                        ->columns(2)
                        ->schema([
                            TextEntry::make('vendor_code')->label('Vendor No.')->weight('bold'),
                            TextEntry::make('vendor_name')->label('Vendor Name')->weight('bold'),
                            TextEntry::make('email')->icon('heroicon-m-envelope')->copyable(),
                            TextEntry::make('phone')->icon('heroicon-m-phone'),
                            TextEntry::make('tax_id')->label('Tax ID'),
                            TextEntry::make('contact_person')->label('Primary Contact'),
                            TextEntry::make('address')->columnSpanFull()->placeholder('No address recorded'),
                        ]),

                    Section::make('Financial Balances')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('balance')
                                ->money()
                                ->weight('black')
                                ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                            TextEntry::make('overdue_balance')
                                ->label('Overdue Amount')
                                ->money()
                                ->color('danger'),

                            TextEntry::make('available_credit')
                                ->label('Unapplied Credit')
                                ->money()
                                ->color('info'),
                        ]),
                ]),

                Section::make('Accounting Setup')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('generalBusinessPostingGroup.code')->label('Gen. Bus. Group')->badge(),
                        TextEntry::make('vendorPostingGroup.code')->label('Vendor Group')->badge(),
                        TextEntry::make('vatBusinessPostingGroup.code')->label('VAT Group')->badge(),
                        TextEntry::make('currency')->label('Default Currency'),
                    ]),

                Section::make('Status & Settings')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('is_active')->label('Relationship Active')->boolean(),
                        IconEntry::make('blocked')->label('Blocked from Posting')->boolean(),
                        TextEntry::make('blocked_reason')->visible(fn($record) => $record->blocked)->color('danger'),
                        TextEntry::make('payment_terms_code')->label('Terms'),
                        TextEntry::make('lead_time_days')->label('Lead Time')->suffix(' Days'),
                    ]),

                Section::make('Internal Notes')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('notes')->markdown()->placeholder('No internal notes.'),
                    ]),
            ]);
    }
}
