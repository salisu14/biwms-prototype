<?php

namespace App\Filament\Sales\Resources\Items;

use App\Enums\ItemType;
use App\Filament\Resources\Items\Schemas\ItemForm;
use App\Filament\Resources\Items\Schemas\ItemInfolist;
use App\Filament\Resources\Items\Tables\ItemsTable;
use App\Filament\Sales\Resources\Items\Pages\CreateItem;
use App\Filament\Sales\Resources\Items\Pages\EditItem;
use App\Filament\Sales\Resources\Items\Pages\ListItems;
use App\Filament\Sales\Resources\Items\Pages\ViewItem;
use App\Models\Item;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cube;

    protected static ?string $recordTitleAttribute = 'description';

    protected static string|null|\UnitEnum $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'Product';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('viewAny', Item::class);
    }

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('item_type', ItemType::FINISHED_GOOD)
            ->where('blocked', false)
            ->where('sales_blocked', false);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'view' => ViewItem::route('/{record}'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }
}
