<?php

namespace App\Filament\Resources\WarehouseShipments\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseShipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Identification')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('document_number')
                                ->label('Shipment No.')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'OPEN' => 'gray',
                                    'RELEASED' => 'info',
                                    'PARTIALLY_SHIPPED' => 'warning',
                                    'SHIPPED' => 'success',
                                    default => 'gray',
                                }),
                            TextEntry::make('location.name')
                                ->label('Shipping Location'),
                        ]),
                    ]),

                Section::make('Shipment Lines')
                    ->description('Inventory items scheduled for outbound shipment.')
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->label('')
                            ->schema([
                                Grid::make(6)->schema([
                                    TextEntry::make('item.item_number')
                                        ->label('Item')
                                        ->weight('bold'),
                                    TextEntry::make('quantity')
                                        ->numeric(4)
                                        ->label('To Ship'),
                                    TextEntry::make('quantity_picked')
                                        ->numeric(4)
                                        ->label('Picked')
                                        ->color('info'),
                                    TextEntry::make('quantity_shipped')
                                        ->numeric(4)
                                        ->label('Shipped')
                                        ->color('success'),
                                    TextEntry::make('quantity_outstanding')
                                        ->numeric(4)
                                        ->label('Outstanding')
                                        ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),
                                    TextEntry::make('bin_code')
                                        ->label('Bin')
                                        ->placeholder('N/A'),
                                ]),
                            ])
                            ->grid(1),
                    ]),

                Section::make('Customer & Source')
                    ->description('Details of the ordering customer and original document.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('customer.name')
                                ->label('Customer Name')
                                ->weight('bold'),
                            Grid::make(3)->schema([
                                TextEntry::make('source_document')
                                    ->label('Source Type'),
                                TextEntry::make('source_document_number')
                                    ->label('Source Doc No.'),
                                TextEntry::make('source_document_id')
                                    ->label('Internal ID'),
                            ]),
                        ]),
                    ]),

                Section::make('Shipping Logistics')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('shipping_agent_code')->label('Agent'),
                            TextEntry::make('shipping_agent_service_code')->label('Service'),
                            TextEntry::make('external_document_number')->label('Tracking No.')->copyable(),
                        ]),
                        Grid::make(3)->schema([
                            TextEntry::make('shipment_date')->date(),
                            TextEntry::make('planned_delivery_date')
                                ->date()
                                ->placeholder('Not specified'),
                            TextEntry::make('posted_date')
                                ->dateTime()
                                ->placeholder('Not yet posted'),
                        ]),
                    ]),

                Section::make('System & Audit')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('assignedUser.name')
                                ->label('Assigned Handler')
                                ->placeholder('Unassigned'),
                            Grid::make(2)->schema([
                                TextEntry::make('created_at')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->dateTime(),
                            ]),
                        ]),
                    ]),
            ]);
    }
}
