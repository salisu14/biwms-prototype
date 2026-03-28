<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\PurchaseOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('order_number'),
                TextEntry::make('order_type')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('vendor.id')
                    ->label('Vendor'),
                TextEntry::make('vendor_name'),
                TextEntry::make('order_date')
                    ->date(),
                TextEntry::make('location.id')
                    ->label('Location'),
                TextEntry::make('posting_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('due_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('delivery_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('payment_terms')
                    ->placeholder('-'),
                TextEntry::make('comment')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('total_vat')
                    ->numeric(),
                TextEntry::make('grand_total')
                    ->numeric(),
                TextEntry::make('created_by')
                    ->numeric(),
                TextEntry::make('approved_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (PurchaseOrder $record): bool => $record->trashed()),
            ]);
    }
}
