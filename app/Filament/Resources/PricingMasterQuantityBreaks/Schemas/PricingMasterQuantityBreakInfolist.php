<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks\Schemas;

use App\Filament\Resources\PricingMasters\PricingMasterResource;
use App\Models\PricingMasterQuantityBreak;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class PricingMasterQuantityBreakInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Quantity Break Details')
                    ->schema([
                        TextEntry::make('pricingMaster.price_list_code')
                            ->label('Pricing Master')
                            ->state(function (PricingMasterQuantityBreak $record): string {
                                if (! $record->pricingMaster) {
                                    return '-';
                                }

                                return "{$record->pricingMaster->price_list_code} - {$record->pricingMaster->description}";
                            })
                            ->url(fn (PricingMasterQuantityBreak $record): ?string => $record->pricingMaster
                                ? PricingMasterResource::getUrl('view', ['record' => $record->pricingMaster])
                                : null)
                            ->placeholder('-'),
                        TextEntry::make('pricingMaster.description')->label('Pricing Master Description')->placeholder('-'),
                        TextEntry::make('line_number')->label('Line No.'),
                        TextEntry::make('minimum_quantity')->numeric()->label('Min Qty'),
                        TextEntry::make('maximum_quantity')->numeric()->label('Max Qty')->placeholder('Unlimited'),
                        TextEntry::make('unit_of_measure_code')->label('UoM')->placeholder('-'),
                        TextEntry::make('unit_price')
                            ->formatStateUsing(function ($state, PricingMasterQuantityBreak $record): string {
                                if ($state === null) {
                                    return '-';
                                }

                                return Number::currency((float) $state, $record->pricingMaster?->currency_code ?? config('app.default_currency', 'USD'));
                            })
                            ->placeholder('-'),
                        TextEntry::make('discount_percent')->suffix('%')->placeholder('-'),
                        TextEntry::make('discount_amount')
                            ->formatStateUsing(function ($state, PricingMasterQuantityBreak $record): string {
                                if ($state === null) {
                                    return '-';
                                }

                                return Number::currency((float) $state, $record->pricingMaster?->currency_code ?? config('app.default_currency', 'USD'));
                            })
                            ->placeholder('-'),
                        TextEntry::make('tier_summary')
                            ->label('Tier Summary')
                            ->state(fn (PricingMasterQuantityBreak $record): string => $record->getTierDescription($record->pricingMaster?->currency_code)),
                    ])->columns(3),
            ]);
    }
}
