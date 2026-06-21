<?php

namespace App\Filament\Resources\PriceLists\Schemas;

use App\Models\PriceList;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class PriceListInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scope')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('item.item_code')->label('Item Code')->placeholder('-')->badge()->color('primary'),
                        TextEntry::make('item.description')->label('Item Description')->placeholder('-'),
                        TextEntry::make('customer.customer_number')
                            ->label('Customer')
                            ->formatStateUsing(fn ($state, PriceList $record): string => $record->customer ? "{$record->customer->customer_number} - {$record->customer->name}" : 'All Customers')
                            ->placeholder('All Customers'),
                        TextEntry::make('customerGroup.code')
                            ->label('Customer Group')
                            ->formatStateUsing(fn ($state, PriceList $record): string => $record->customerGroup ? "{$record->customerGroup->code} - {$record->customerGroup->name}" : 'All Groups')
                            ->placeholder('All Groups'),
                    ])->columns(2),

                Section::make('Pricing')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        TextEntry::make('price')
                            ->label('Price')
                            ->formatStateUsing(fn ($state, PriceList $record): string => Number::currency((float) $state, $record->currency)),
                        TextEntry::make('currency')->badge()->color('gray'),
                        TextEntry::make('starting_date')->date('d/m/Y')->label('Starts'),
                        TextEntry::make('ending_date')->date('d/m/Y')->label('Ends')->placeholder('No End Date'),
                    ])->columns(2),

                Section::make('Status')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'scheduled' => 'info',
                                'expired' => 'danger',
                                default => 'gray',
                            })
                            ->state(function (PriceList $record): string {
                                $started = $record->starting_date <= now();
                                $expired = $record->ending_date && $record->ending_date < now();

                                if ($expired) {
                                    return 'expired';
                                }

                                if ($started) {
                                    return 'active';
                                }

                                return 'scheduled';
                            }),
                    ])->columns(3),
            ]);
    }
}
