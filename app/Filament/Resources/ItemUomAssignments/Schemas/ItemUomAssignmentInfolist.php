<?php

namespace App\Filament\Resources\ItemUomAssignments\Schemas;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\UnitOfMeasures\UnitOfMeasureResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemUomAssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('item.item_code')
                            ->label('Item')
                            ->weight('bold')
                            ->formatStateUsing(fn ($state, $record): string => $record->item
                                ? "{$record->item->item_code} - {$record->item->description}"
                                : '—')
                            ->url(fn ($record): ?string => $record->item
                                ? ItemResource::getUrl('view', ['record' => $record->item])
                                : null),

                        TextEntry::make('uom.uom_code')
                            ->label('Unit of Measure')
                            ->weight('bold')
                            ->formatStateUsing(fn ($state, $record): string => $record->uom
                                ? "{$record->uom->uom_code} - {$record->uom->description}"
                                : '—')
                            ->url(fn ($record): ?string => $record->uom
                                ? UnitOfMeasureResource::getUrl('view', ['record' => $record->uom])
                                : null),

                        TextEntry::make('uom_type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state, $record): string => $record->uom_type_label),

                        TextEntry::make('is_default')
                            ->label('Default')
                            ->badge()
                            ->getStateUsing(fn ($record): bool => (bool) $record->is_default)
                            ->formatStateUsing(fn ($state): string => $state ? 'Yes' : 'No'),
                    ]),

                Section::make('Conversion')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('conversion_factor')
                            ->label('Qty. per UoM')
                            ->numeric(6),
                        TextEntry::make('sort_order')
                            ->label('Sort Order')
                            ->numeric(),
                    ]),
            ]);
    }
}
