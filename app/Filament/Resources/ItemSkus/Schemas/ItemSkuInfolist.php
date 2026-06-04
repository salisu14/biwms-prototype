<?php

namespace App\Filament\Resources\ItemSkus\Schemas;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Locations\LocationResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemSkuInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('sku_code')
                            ->label('SKU')
                            ->weight('bold'),

                        TextEntry::make('barcode')
                            ->label('Barcode')
                            ->copyable()
                            ->placeholder('-'),

                        TextEntry::make('item.item_code')
                            ->label('Item')
                            ->formatStateUsing(fn ($state, $record): string => $record->item
                                ? "{$record->item->item_code} - {$record->item->description}"
                                : '—')
                            ->url(fn ($record): ?string => $record->item
                                ? ItemResource::getUrl('view', ['record' => $record->item])
                                : null),

                        TextEntry::make('location.name')
                            ->label('Location')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn ($state, $record): string => $record->location
                                ? "{$record->location->code} - {$record->location->name}"
                                : '—')
                            ->url(fn ($record): ?string => $record->location
                                ? LocationResource::getUrl('view', ['record' => $record->location])
                                : null),
                    ]),

                Section::make('Inventory')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('current_quantity')
                            ->label('Current Quantity')
                            ->badge()
                            ->color(fn ($record): string => $record->needs_reorder ? 'danger' : 'success')
                            ->suffix(' qty'),

                        IconEntry::make('needs_reorder')
                            ->label('Reorder Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-exclamation-triangle')
                            ->falseIcon('heroicon-o-check')
                            ->trueColor('danger')
                            ->falseColor('success'),

                        TextEntry::make('reorder_point')
                            ->label('Reorder Point')
                            ->numeric()
                            ->suffix(' qty'),

                        TextEntry::make('safety_stock')
                            ->label('Safety Stock')
                            ->numeric()
                            ->suffix(' qty'),

                        TextEntry::make('lead_time_days')
                            ->label('Lead Time')
                            ->numeric()
                            ->suffix(' days'),
                    ]),

                Section::make('Validity & Status')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('effective_date')
                            ->label('Effective Date')
                            ->date()
                            ->placeholder('Immediately'),

                        TextEntry::make('expiry_date')
                            ->label('Expiry Date')
                            ->date()
                            ->placeholder('Never'),

                        TextEntry::make('is_effective')
                            ->label('Currently Effective')
                            ->getStateUsing(fn ($record): bool => $record->is_effective)
                            ->badge()
                            ->color(fn ($state): string => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'),

                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ]),

                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Created At'),

                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->label('Last Updated'),
                    ])
                    ->collapsible(),
            ]);
    }
}
