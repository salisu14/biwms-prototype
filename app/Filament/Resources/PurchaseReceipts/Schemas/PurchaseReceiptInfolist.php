<?php

namespace App\Filament\Resources\PurchaseReceipts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseReceiptInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Left Column (Receipt Details, Origin, Shipping)
                Group::make([
                    Section::make('Receipt Details')
                        ->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('document_number')
                                    ->label('Receipt No.')
                                    ->weight('bold'),
                                TextEntry::make('purchase_order_no')
                                    ->label('Order No.'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('buy_from_vendor_name')
                                    ->label('Vendor'),
                                TextEntry::make('external_document_no')
                                    ->label('Vendor Shipment No.')
                                    ->placeholder('-'),
                                TextEntry::make('receivingLocation.name')
                                    ->label('Receiving Location'),
                            ]),
                        ]),

                    // Nested Grid to handle side-by-side sections (Origin vs Shipping)
                    Grid::make(2)->schema([
                        Section::make('Origin Address')
                            ->schema([
                                TextEntry::make('buy_from_address')->label('Address'),
                                TextEntry::make('buy_from_city')->label('City'),
                                TextEntry::make('buy_from_post_code')->label('Post Code'),
                            ])->columnSpan(1),
                        Section::make('Shipping Info')
                            ->schema([
                                TextEntry::make('shipment_method_code')->label('Method'),
                                TextEntry::make('shipping_agent_code')->label('Agent'),
                                TextEntry::make('package_tracking_no')->label('Tracking No.')->copyable(),
                            ])->columnSpan(1),
                    ]),
                ]), // Removed ->grow() as Grid handles this

                // Right Column (Dates, Audit)
                Group::make([
                    Section::make('Dates')
                        ->schema([
                            TextEntry::make('posting_date')->date(),
                            TextEntry::make('document_date')->date(),
                            TextEntry::make('actual_receipt_date')->date()->label('Received At'),
                        ]),
                    Section::make('Posting Audit')
                        ->schema([
                            IconEntry::make('posted')->boolean(),
                            TextEntry::make('postedByUser.name')->label('Posted By'),
                            TextEntry::make('posted_at')->dateTime(),
                        ]),
                ]), // Removed ->columnSpan(1) as Grid::make(2) handles this
            ]);

    }
}
