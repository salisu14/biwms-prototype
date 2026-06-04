<?php

namespace App\Filament\Resources\PricingMasters\RelationManagers;

use App\Filament\Resources\PricingMasterQuantityBreaks\PricingMasterQuantityBreakResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class QuantityBreaksRelationManager extends RelationManager
{
    protected static string $relationship = 'quantityBreaks';

    protected static ?string $relatedResource = PricingMasterQuantityBreakResource::class;

    protected static ?string $title = 'Quantity Breaks';

    public function table(Table $table): Table
    {
        $currencyCode = $this->getOwnerRecord()->currency_code ?? config('app.default_currency', 'USD');

        return $table
            ->columns([
                TextColumn::make('tier_summary')
                    ->label('Quantity Break')
                    ->state(fn ($record) => $record->getTierDescription($currencyCode))
                    ->badge()
                    ->color('gray')
                    ->weight('bold')
                    ->wrap()
                    ->description(fn ($record) => $record->unit_of_measure_code ? "UoM: {$record->unit_of_measure_code}" : 'UoM: -'),

                TextColumn::make('line_number')
                    ->label('Line')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('range')
                    ->label('Range')
                    ->state(function ($record): string {
                        $minimumQuantity = number_format((float) $record->minimum_quantity, 0);
                        $maximumQuantity = $record->maximum_quantity ? number_format((float) $record->maximum_quantity, 0) : 'Unlimited';

                        return "{$minimumQuantity} - {$maximumQuantity}";
                    }),

                TextColumn::make('unit_of_measure_code')
                    ->label('UoM')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('pricing_rule')
                    ->label('Pricing Rule')
                    ->state(function ($record) use ($currencyCode): string {
                        if ($record->unit_price !== null) {
                            return Number::currency((float) $record->unit_price, $currencyCode);
                        }

                        if ($record->discount_percent !== null) {
                            return $record->discount_percent.'% discount';
                        }

                        if ($record->discount_amount !== null) {
                            return '-'.Number::currency((float) $record->discount_amount, $currencyCode);
                        }

                        return '—';
                    })
                    ->alignEnd(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
