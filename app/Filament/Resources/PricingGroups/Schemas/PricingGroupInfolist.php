<?php

namespace App\Filament\Resources\PricingGroups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PricingGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Group Details')
                    ->schema([
                        TextEntry::make('code')->badge()->color('primary'),
                        TextEntry::make('name')->weight('bold'),
                        TextEntry::make('description')->placeholder('-')->columnSpanFull(),
                    ])->columns(2),

                Section::make('Pricing Strategy & Margins')
                    ->schema([
                        TextEntry::make('pricing_strategy')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn ($state) => str_replace('_', ' ', $state)),
                        TextEntry::make('default_discount_percent')
                            ->suffix('%')
                            ->placeholder('-'),
                        TextEntry::make('default_markup_percent')
                            ->suffix('%')
                            ->placeholder('-'),
                        IconEntry::make('enforce_minimum_margin')
                            ->label('Enforces Margin?')
                            ->boolean(),
                        TextEntry::make('minimum_margin_percent')
                            ->label('Minimum Margin')
                            ->suffix('%')
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->enforce_minimum_margin),
                        IconEntry::make('allow_manual_override')
                            ->label('Manual Override?')
                            ->boolean(),
                    ])->columns(3),

                Section::make('Validity & Accounting')
                    ->schema([
                        TextEntry::make('currency_code')->badge()->color('gray'),
                        TextEntry::make('start_date')->date('d/m/Y')->placeholder('No Start Date'),
                        TextEntry::make('end_date')->date('d/m/Y')->placeholder('No End Date'),
                        TextEntry::make('generalBusinessPostingGroup.code') // Adjust if your attribute is 'name' instead of 'code'
                        ->label('Gen. Bus. Posting Group')
                            ->placeholder('-')
                            ->badge(),
                        IconEntry::make('blocked')->boolean(),
                    ])->columns(3),
            ]);
    }
}
