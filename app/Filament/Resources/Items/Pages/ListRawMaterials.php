<?php

namespace App\Filament\Resources\Items\Pages;

use App\Enums\ItemType;
use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListRawMaterials extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected static ?string $title = 'Raw & Packaging Materials';

    protected static ?string $navigationLabel = 'Raw & Packaging Materials';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-cube';

    protected static string|null|\UnitEnum $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Item::query()
                    ->whereIn('item_type', [
                        ItemType::RAW_MATERIAL->value,
                        ItemType::PACKAGING->value,
                    ])
            )
            ->columns(ItemResource::table($table)->getColumns());
    }
}
