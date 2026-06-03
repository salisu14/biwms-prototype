<?php

namespace App\Filament\Resources\Items;

use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Items\Pages\ViewItem;
use App\Filament\Resources\Items\Schemas\ItemForm;
use App\Filament\Resources\Items\Schemas\ItemInfolist;
use App\Filament\Resources\Items\Tables\ItemsTable;
use App\Models\Item;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return ItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Item) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'item_code',
            'sku',
            'description',
            'description_2',
            'primaryCategory.category_name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Item $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Item $record */
        return [
            'Type' => $record->item_type?->value ?? '—',
            'SKU' => $record->sku ?: '—',
            'Price' => number_format((float) $record->unit_price, 2),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('primaryCategory');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'view' => ViewItem::route('/{record}'),
            'edit' => EditItem::route('/{record}/edit'),

            // Custom filtered pages
            'raw-materials' => Pages\ListRawMaterials::route('rm/raw-materials'),
            'finished-goods' => Pages\ListFinishedGoods::route('fg/finished-goods'),
        ];
    }

    protected static function formatRecordTitle(Item $record): string
    {
        $itemCode = $record->item_code ?: 'Unknown Item';
        $description = $record->description ?: 'No description';

        return "{$itemCode} - {$description}";
    }
}
