<?php

namespace App\Filament\Resources\DepreciationBooks\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepreciationBookInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Book Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->weight('bold'),
                        TextEntry::make('description'),
                        TextEntry::make('book_type')
                            ->badge(),
                        IconEntry::make('is_active')
                            ->boolean(),
                        IconEntry::make('is_default')
                            ->label('Default Book')
                            ->boolean(),
                    ]),

                Section::make('Logic & Methods')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('default_depreciation_method')
                            ->badge(),
                        TextEntry::make('default_calculation_method')
                            ->badge(),
                    ]),

                Section::make('Financial Controls')
                    ->columns(3)
                    ->schema([
                        IconEntry::make('integrate_with_gl')
                            ->label('G/L Integration')
                            ->boolean(),
                        IconEntry::make('use_rounding')
                            ->boolean(),
                        TextEntry::make('rounding_precision')
                            ->state(fn ($record) => $record->use_rounding ? $record->rounding_precision : 'N/A'),
                    ]),
            ]);
    }
}
