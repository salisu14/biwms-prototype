<?php

namespace App\Filament\Resources\CustomerPriceOverrides\Schemas;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Items\ItemResource;
use App\Models\CustomerPriceOverride;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class CustomerPriceOverrideInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scope')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer_label')
                            ->label('Customer')
                            ->state(function (CustomerPriceOverride $record): string {
                                return $record->customer
                                    ? "{$record->customer->customer_number} - {$record->customer->name}"
                                    : '—';
                            })
                            ->url(fn (CustomerPriceOverride $record): ?string => $record->customer
                                ? CustomerResource::getUrl('view', ['record' => $record->customer])
                                : null),
                        TextEntry::make('item_label')
                            ->label('Item')
                            ->state(function (CustomerPriceOverride $record): string {
                                return $record->item
                                    ? "{$record->item->item_code} - {$record->item->description}"
                                    : '—';
                            })
                            ->url(fn (CustomerPriceOverride $record): ?string => $record->item
                                ? ItemResource::getUrl('view', ['record' => $record->item])
                                : null),
                    ]),

                Section::make('Pricing')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('base_price')
                            ->label('Base Item Price')
                            ->state(fn (CustomerPriceOverride $record): string => Number::currency((float) ($record->item?->unit_price ?? 0), config('app.default_currency', 'USD'))),
                        TextEntry::make('override_price')
                            ->label('Override Price')
                            ->state(fn (CustomerPriceOverride $record): string => Number::currency((float) $record->override_price, config('app.default_currency', 'USD')))
                            ->badge()
                            ->color('success'),
                        TextEntry::make('variance')
                            ->label('Variance')
                            ->state(function (CustomerPriceOverride $record): string {
                                $basePrice = (float) ($record->item?->unit_price ?? 0);
                                $overridePrice = (float) $record->override_price;
                                $difference = $overridePrice - $basePrice;

                                return Number::currency($difference, config('app.default_currency', 'USD'));
                            })
                            ->badge()
                            ->color(fn (CustomerPriceOverride $record): string => $record->override_price < (float) ($record->item?->unit_price ?? 0) ? 'success' : 'danger'),
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
