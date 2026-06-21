<?php

namespace App\Filament\Resources\PurchasePrices\Schemas;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\PurchasePrice;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class PurchasePriceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scope')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('vendor_label')
                            ->label('Vendor')
                            ->state(function (PurchasePrice $record): string {
                                return $record->vendor
                                    ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
                                    : '—';
                            })
                            ->url(fn (PurchasePrice $record): ?string => $record->vendor
                                ? VendorResource::getUrl('view', ['record' => $record->vendor])
                                : null),
                        TextEntry::make('item_label')
                            ->label('Item')
                            ->state(function (PurchasePrice $record): string {
                                return $record->item
                                    ? "{$record->item->item_code} - {$record->item->description}"
                                    : '—';
                            })
                            ->url(fn (PurchasePrice $record): ?string => $record->item
                                ? ItemResource::getUrl('view', ['record' => $record->item])
                                : null),
                    ]),

                Section::make('Pricing')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('direct_unit_cost')
                            ->label('Direct Unit Cost')
                            ->state(fn (PurchasePrice $record): string => Number::currency((float) $record->direct_unit_cost, config('app.default_currency', 'USD'))),
                        TextEntry::make('line_discount_percent')
                            ->label('Line Discount %')
                            ->badge()
                            ->color('success')
                            ->state(fn (PurchasePrice $record): string => number_format((float) $record->line_discount_percent, 2).'%'),
                        TextEntry::make('minimum_quantity')
                            ->label('Minimum Quantity')
                            ->numeric(),
                        TextEntry::make('unit_of_measure_code')
                            ->label('Unit of Measure')
                            ->placeholder('Base UoM'),
                        TextEntry::make('vendor_item_no')
                            ->label('Vendor Item No.')
                            ->placeholder('—'),
                        TextEntry::make('validity')
                            ->label('Validity')
                            ->state(function (PurchasePrice $record): string {
                                $start = $record->starting_date?->format('d/m/Y') ?? 'Immediate';
                                $end = $record->ending_date?->format('d/m/Y') ?? 'Open';

                                return "{$start} - {$end}";
                            }),
                    ]),

                Section::make('Metadata')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->dateTime()->label('Created At'),
                        TextEntry::make('updated_at')->dateTime()->label('Updated At'),
                    ]),
            ]);
    }
}
