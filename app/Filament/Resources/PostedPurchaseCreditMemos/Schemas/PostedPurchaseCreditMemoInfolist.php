<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostedPurchaseCreditMemoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        // Left Column
                        Group::make([
                            Section::make('General Information')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('document_number')
                                            ->label('Document No.')
                                            ->weight('bold'),
                                        IconEntry::make('posted')
                                            ->boolean(),
                                        TextEntry::make('vendor_name')
                                            ->label('Vendor Name'),
                                        TextEntry::make('vendor_tax_registration_number')
                                            ->label('Tax Reg #'),
                                        TextEntry::make('external_document_number')
                                            ->label('External Doc No.')
                                            ->placeholder('-'),
                                        TextEntry::make('corrects_invoice_number')
                                            ->label('Corrected Invoice')
                                            ->placeholder('-'),
                                    ]),
                                ]),
                            Section::make('Address & Logistics')
                                ->schema([
                                    TextEntry::make('vendor_address')
                                        ->label('Address'),
                                    Grid::make(3)->schema([
                                        TextEntry::make('vendor_city')->label('City'),
                                        TextEntry::make('vendor_post_code')->label('Post Code'),
                                        TextEntry::make('location_code')->label('Location'),
                                    ]),
                                ]),
                        ]), // Removed ->grow() as Grid handles layout naturally

                        // Right Column
                        Group::make([
                            Section::make('Financial Summary')
                                ->schema([
                                    TextEntry::make('grand_total')
                                        ->money(fn ($record) => $record->currency_code)
                                        ->size('lg')
                                        ->weight('bold'),
                                    TextEntry::make('tax_amount')
                                        ->label('Tax Amount')
                                        ->money(fn ($record) => $record->currency_code),
                                    TextEntry::make('currency_code')->label('Currency'),
                                ]),
                            Section::make('Posting Dates')
                                ->schema([
                                    TextEntry::make('posting_date')->date(),
                                    TextEntry::make('document_date')->date(),
                                    TextEntry::make('due_date')->date(),
                                    TextEntry::make('posted_at')->dateTime(),
                                ]),
                        ]), // Removed ->columnSpan(1) as Grid::make(2) handles this
                    ]),

                Section::make('Audit Details')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('posted_by')->label('Posted By User ID'),
                            TextEntry::make('vendorPostingGroup.vendor_posting_group_code')->label('AP Group'),
                            TextEntry::make('generalBusinessPostingGroup.code')->label('Bus. Group'),
                        ]),
                    ]),
            ]);
    }
}
