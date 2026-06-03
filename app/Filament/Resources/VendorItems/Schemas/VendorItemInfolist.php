<?php

namespace App\Filament\Resources\VendorItems\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment & Catalog')
                    ->icon('heroicon-o-link')
                    ->schema([
                        TextEntry::make('vendor.vendor_name')->label('Vendor'),
                        TextEntry::make('item.item_code')->label('Item Code'),
                        TextEntry::make('item.description')->label('Item Description'),
                        TextEntry::make('vendor_item_number')->label('Vendor SKU'),
                        TextEntry::make('vendor_item_name')->label('Vendor Name')->placeholder('-'),
                        TextEntry::make('vendor_item_category')->label('Vendor Category')->placeholder('-')->badge(),
                    ])->columns(3),

                Section::make('Purchasing & Pricing')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        TextEntry::make('purchaseUom.uom_code')->label('Purchase UoM')->placeholder('-'),
                        TextEntry::make('minimum_order_qty')->label('MOQ')->numeric(),
                        TextEntry::make('lead_time_days')->label('Lead Time')->suffix(' days'),
                        TextEntry::make('unit_cost')->label('Unit Cost')->money(fn ($record) => $record->currency?->code ?? 'NGN'),
                        TextEntry::make('last_purchase_price')->label('Last Price')->money(fn ($record) => $record->currency?->code ?? 'NGN')->placeholder('-'),
                        TextEntry::make('last_purchase_date')->label('Last Purchased')->date('d/m/Y')->placeholder('-'),
                        TextEntry::make('price_breaks')
                            ->label('Price Breaks')
                            ->formatStateUsing(function ($record) {
                                if (empty($record->price_breaks)) return 'None';
                                return collect($record->price_breaks)
                                    ->sortKeys()
                                    ->map(fn ($price, $qty) => "{$qty}+ units @ " . number_format($price, 2))
                                    ->implode(' | ');
                            })
                            ->columnSpanFull(),
                    ])->columns(3),

                Section::make('Status & Validity')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        IconEntry::make('is_active')->label('Active?')->boolean(),
                        IconEntry::make('is_preferred')->label('Preferred Vendor?')->boolean(),
                        IconEntry::make('is_currently_effective')
                            ->label('Currently Effective?')
                            ->boolean()
                            ->state(fn ($record) => $record->is_currently_effective)
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('effective_date')->label('Effective Date')->date('d/m/Y')->placeholder('-'),
                        TextEntry::make('expiry_date')->label('Expiry Date')->date('d/m/Y')->placeholder('-'),
                    ])->columns(3),
            ]);
    }
}
