<?php

namespace App\Filament\Resources\SalesShipmentHeaders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;

class SalesShipmentHeaderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Split::make([
                    Grid::make(2)->schema([
                        Section::make('General Information')
                            ->schema([
                                TextEntry::make('document_no')->label('No.'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('sell_to_customer_name')->label('Customer'),
                                TextEntry::make('posting_date')->date(),
                                TextEntry::make('order_no')->label('Source Order'),
                            ])->columns(2),

                        Tabs::make('Details')
                            ->tabs([
                                Tab::make('Address')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('sell_to_address')->label('Address'),
                                            TextEntry::make('sell_to_city')->label('City'),
                                            TextEntry::make('ship_to_name')->label('Ship-to Name'),
                                            TextEntry::make('ship_to_address')->label('Ship-to Address'),
                                        ]),
                                    ]),
                                Tab::make('Logistics')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('location_code'),
                                            TextEntry::make('shipping_agent_code'),
                                            TextEntry::make('package_tracking_no'),
                                            TextEntry::make('shipment_method_code'),
                                        ]),
                                    ]),
                            ]),
                    ])->grow(),

                    Section::make('Audit')
                        ->schema([
                            TextEntry::make('user_id')->label('Created By'),
                            TextEntry::make('created_at')->dateTime(),
                            TextEntry::make('updated_at')->dateTime(),
                        ])->columnSpan(1),
                ])->from('lg')->columnSpanFull(),
            ]);
    }
}
