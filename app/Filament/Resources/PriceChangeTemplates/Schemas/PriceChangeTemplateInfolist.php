<?php

namespace App\Filament\Resources\PriceChangeTemplates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class PriceChangeTemplateInfolist
{
    protected static function isPercentageAdjustment(string $adjustmentType): bool
    {
        return in_array($adjustmentType, ['increase', 'decrease'], true);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->columnSpan(2)
                            ->weight('bold')
                            ->size(TextSize::Large),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'approved' => 'warning',
                                'applied' => 'success',
                                default => 'gray',
                            }),

                        TextEntry::make('adjustment_type')
                            ->label('Adjustment Type')
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                        TextEntry::make('value')
                            ->label('Value')
                            ->formatStateUsing(fn ($record, $state) => self::isPercentageAdjustment((string) $record->adjustment_type) ? $state.'%' : '₦'.number_format((float) $state, 2)),

                        TextEntry::make('base')
                            ->formatStateUsing(fn (string $state): string => 'Based on '.ucfirst($state)),

                        TextEntry::make('rounding')
                            ->label('Rounding decimals')
                            ->placeholder('None'),
                    ]),

                Section::make('Lifecycle')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('effective_from')
                            ->date()
                            ->label('Valid From')
                            ->icon('heroicon-m-calendar'),
                        TextEntry::make('effective_to')
                            ->date()
                            ->label('Valid To')
                            ->icon('heroicon-m-calendar-days'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->color('gray'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->color('gray'),
                    ]),
            ]);
    }
}
