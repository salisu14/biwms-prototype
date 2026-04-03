<?php

namespace App\Filament\Resources\ProductionBomVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionBomVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Version Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('productionBom.description')
                            ->label('Production BOM'),
                        TextEntry::make('version_code')
                            ->label('Version Code')
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),

                Grid::make(2)->schema([
                    Section::make('Validity & Logistics')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('starting_date')
                                ->date()
                                ->icon('heroicon-m-play'),
                            TextEntry::make('ending_date')
                                ->date()
                                ->icon('heroicon-m-stop')
                                ->placeholder('No end date'),
                            TextEntry::make('unit_of_measure_code')
                                ->label('Unit of Measure'),
                            TextEntry::make('quantity_per')
                                ->numeric(decimalPlaces: 4),
                        ]),

                    Section::make('Audit Information')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('cost_rollup')
                                ->money('USD'),
                            TextEntry::make('created_at')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->dateTime(),
                            TextEntry::make('creator.name')
                                ->label('Created By')
                                ->placeholder('System'),
                        ]),


                ])
            ]);
    }

    #Automatically utilizes the `HasLabel`, `HasColor`, and `HasIcon` interfaces defined in the enum above. Because the `status` field in your model is cast to this Enum, Filament's `badge()` method will:
    #1.  Display the user-friendly label (e.g., "Certified (Active)").
    #2.  Apply the semantic color (e.g., Green for Certified).
    #3.  Display the associated icon (e.g., Check Badge).

    #This provides a high-end visual experience in the Infolist without needing to hardcode colors or icons inside the schema class itself.#
}
