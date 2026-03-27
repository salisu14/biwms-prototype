<?php

namespace App\Filament\Resources\ItemMasters;

use App\Filament\Resources\ItemMasters\Pages\CreateItemMaster;
use App\Filament\Resources\ItemMasters\Pages\EditItemMaster;
use App\Filament\Resources\ItemMasters\Pages\ListItemMasters;
use App\Filament\Resources\ItemMasters\Pages\ViewItemMaster;
use App\Filament\Resources\ItemMasters\Schemas\ItemMasterForm;
use App\Filament\Resources\ItemMasters\Schemas\ItemMasterInfolist;
use App\Filament\Resources\ItemMasters\Tables\ItemMastersTable;
use App\Models\ItemMaster;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItemMasterResource extends Resource
{
    protected static ?string $model = ItemMaster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ItemMasterForm::configure($schema);
    }

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract categories and primary_category_id before creation
        $categories = $data['categories'] ?? [];
        $primaryCategoryId = $data['primary_category_id'] ?? ($categories[0] ?? null);

        // Store in a temporary property for afterCreate hook
        static::$pendingCategories = [
            'ids' => $categories,
            'primary' => $primaryCategoryId,
        ];

        unset($data['categories'], $data['primary_category_id']);
        return $data;
    }

    protected static function mutateFormDataBeforeUpdate(array $data): array
    {
        // Same for update
        static::$pendingCategories = [
            'ids' => $data['categories'] ?? [],
            'primary' => $data['primary_category_id'] ?? ($data['categories'][0] ?? null),
        ];

        unset($data['categories'], $data['primary_category_id']);
        return $data;
    }

    protected static ?array $pendingCategories = null;

    protected static function afterCreate(ItemMaster $record): void
    {
        if (!empty(static::$pendingCategories['ids'])) {
            $syncData = [];
            foreach (static::$pendingCategories['ids'] as $index => $categoryId) {
                $syncData[$categoryId] = [
                    'is_primary' => ($categoryId == static::$pendingCategories['primary']),
                    'sort_order' => $index,
                ];
            }
            $record->categories()->sync($syncData);
        }
        static::$pendingCategories = null;
    }

    protected static function afterUpdate(ItemMaster $record): void
    {
        if (static::$pendingCategories !== null) {
            if (!empty(static::$pendingCategories['ids'])) {
                $syncData = [];
                foreach (static::$pendingCategories['ids'] as $index => $categoryId) {
                    $syncData[$categoryId] = [
                        'is_primary' => ($categoryId == static::$pendingCategories['primary']),
                        'sort_order' => $index,
                    ];
                }
                $record->categories()->sync($syncData);
            } else {
                $record->categories()->detach();
            }
            static::$pendingCategories = null;
        }
    }

    // Handle category sync after creation
    protected static function handleRecordCreation(array $data): ItemMaster
    {
        // Extract categories before creating
        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);

        // Create the item
        $item = static::getModel()::create($data);

        // Sync categories with pivot data
        if (!empty($categoryIds)) {
            $syncData = [];
            foreach ($categoryIds as $index => $categoryId) {
                $syncData[$categoryId] = [
                    'is_primary' => ($index === 0), // First one is primary
                    'sort_order' => $index,
                ];
            }
            $item->categories()->sync($syncData);
        }

        return $item;
    }
    public static function infolist(Schema $schema): Schema
    {
        return ItemMasterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemMastersTable::configure($table);
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
            'index' => ListItemMasters::route('/'),
            'create' => CreateItemMaster::route('/create'),
            'view' => ViewItemMaster::route('/{record}'),
            'edit' => EditItemMaster::route('/{record}/edit'),
        ];
    }
}
