<?php

namespace App\Filament\Resources\ItemCategoryAssignments;

use App\Filament\Resources\ItemCategoryAssignments\Pages\CreateItemCategoryAssignment;
use App\Filament\Resources\ItemCategoryAssignments\Pages\EditItemCategoryAssignment;
use App\Filament\Resources\ItemCategoryAssignments\Pages\ListItemCategoryAssignments;
use App\Filament\Resources\ItemCategoryAssignments\Pages\ViewItemCategoryAssignment;
use App\Filament\Resources\ItemCategoryAssignments\Schemas\ItemCategoryAssignmentForm;
use App\Filament\Resources\ItemCategoryAssignments\Schemas\ItemCategoryAssignmentInfolist;
use App\Filament\Resources\ItemCategoryAssignments\Tables\ItemCategoryAssignmentsTable;
use App\Models\ItemCategoryAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemCategoryAssignmentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'item_category_assignments';
    }

    public static function permissionResource(): string
    {
        return 'item_category_assignment';
    }

    protected static ?string $model = ItemCategoryAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ItemCategoryAssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemCategoryAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemCategoryAssignmentsTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof ItemCategoryAssignment) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'item.item_code',
            'item.description',
            'category.category_code',
            'category.category_name',
            'is_primary',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var ItemCategoryAssignment $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ItemCategoryAssignment $record */
        return [
            'Item' => $record->item
                ? "{$record->item->item_code} - {$record->item->description}"
                : '—',
            'Category' => $record->category
                ? "[{$record->category->category_code}] {$record->category->category_name}"
                : '—',
            'Primary' => $record->is_primary ? 'Yes' : 'No',
            'Order' => (string) $record->sort_order,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['item', 'category']);
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
            'index' => ListItemCategoryAssignments::route('/'),
            'create' => CreateItemCategoryAssignment::route('/create'),
            'view' => ViewItemCategoryAssignment::route('/{record}'),
            'edit' => EditItemCategoryAssignment::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(ItemCategoryAssignment $record): string
    {
        $itemCode = $record->item?->item_code ?: 'Item';
        $categoryCode = $record->category?->category_code ?: 'Category';

        return "{$itemCode} • {$categoryCode}";
    }
}
