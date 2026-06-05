<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource;
use App\Models\SalesOrder;
use App\Models\SalesShipmentHeader;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Group::make([
                            Section::make('Header')
                                ->schema([
                                    TextEntry::make('order_number')->weight('bold')->size('lg'),
                                    TextEntry::make('order_type')->badge(),
                                    TextEntry::make('status')->badge(),
                                    TextEntry::make('external_document_number')->label('External Ref'),
                                ])->columns(2),

                            Section::make('Customer & Shipping')
                                ->schema([
                                    TextEntry::make('customer.name')->label('Billing Customer'),
                                    TextEntry::make('customer_address')->label('Billing Address')->markdown(),
                                    TextEntry::make('ship_to_name')->label('Shipping Recipient'),
                                    TextEntry::make('ship_to_address')->label('Shipping Address')->markdown(),
                                    TextEntry::make('related_shipment_no')
                                        ->label('Related Shipment No.')
                                        ->state(function (SalesOrder $record): string {
                                            return self::resolveRelatedShipment($record)?->document_no ?? '—';
                                        })
                                        ->url(function (SalesOrder $record): ?string {
                                            $shipment = self::resolveRelatedShipment($record);

                                            if (! $shipment) {
                                                return null;
                                            }

                                            return SalesShipmentHeaderResource::getUrl('view', ['record' => $shipment]);
                                        })
                                        ->openUrlInNewTab(),
                                ])->columns(2),
                        ])->columnSpan(2),

                        Group::make([
                            Section::make('Dates')
                                ->schema([
                                    TextEntry::make('order_date')->date(),
                                    TextEntry::make('requested_delivery_date')->date(),
                                    TextEntry::make('shipment_date')->date()->color('primary'),
                                ]),

                            Section::make('Financials')
                                ->schema([
                                    TextEntry::make('grand_total')
                                        ->numeric()
                                        ->weight('bold')
                                        ->size('xl')
                                        ->color('success'),
                                    TextEntry::make('currency_code')->label('Currency'),
                                    Grid::make(2)
                                        ->schema([
                                            IconEntry::make('fully_shipped')->boolean(),
                                            IconEntry::make('fully_invoiced')
                                                ->state(function (SalesOrder $record): bool {
                                                    $record->loadMissing('lines');

                                                    if ($record->status === SalesOrderStatus::INVOICED) {
                                                        return true;
                                                    }

                                                    return $record->lines->isNotEmpty()
                                                        && $record->lines->every(
                                                            fn ($line): bool => (float) $line->quantity_invoiced >= (float) $line->quantity_shipped
                                                        );
                                                })
                                                ->boolean(),
                                        ]),
                                ]),
                        ])->columnSpan(1),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('customer_comment')->label('Customer Comment'),
                        TextEntry::make('internal_comment')->label('Internal Staff Notes'),
                    ])->columns(2),
            ]);
    }

    protected static function resolveRelatedShipment(SalesOrder $record): ?SalesShipmentHeader
    {
        if (blank($record->order_number)) {
            return null;
        }

        return SalesShipmentHeader::query()
            ->where('order_no', $record->order_number)
            ->latest('posting_date')
            ->latest('id')
            ->first();
    }
}
