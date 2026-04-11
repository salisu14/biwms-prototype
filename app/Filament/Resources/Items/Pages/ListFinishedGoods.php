<?php

namespace App\Filament\Resources\Items\Pages;

use App\Enums\ItemType;
use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListFinishedGoods extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected static ?string $title = 'Finished Goods';

    protected static ?string $navigationLabel = 'Finished Goods';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-check-badge';

    protected static string|null|\UnitEnum $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Item::query()->whereIn('item_type', [
                    ItemType::FINISHED_GOOD->value,
                    ItemType::SERVICE->value,
                ])
            )
            ->columns(ItemResource::table($table)->getColumns()); // reuse columns if needed
    }

    protected function modifyQueryUsing(Builder $query): Builder
    {
        return $query->where('item_type', ItemType::FINISHED_GOOD->value);
    }
}
