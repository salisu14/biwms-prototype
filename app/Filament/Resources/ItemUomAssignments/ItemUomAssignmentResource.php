<?php

namespace App\Filament\Resources\ItemUomAssignments;

use App\Filament\Resources\ItemUomAssignments\Pages\CreateItemUomAssignment;
use App\Filament\Resources\ItemUomAssignments\Pages\EditItemUomAssignment;
use App\Filament\Resources\ItemUomAssignments\Pages\ListItemUomAssignments;
use App\Filament\Resources\ItemUomAssignments\Pages\ViewItemUomAssignment;
use App\Filament\Resources\ItemUomAssignments\Schemas\ItemUomAssignmentForm;
use App\Filament\Resources\ItemUomAssignments\Schemas\ItemUomAssignmentInfolist;
use App\Filament\Resources\ItemUomAssignments\Tables\ItemUomAssignmentsTable;
use App\Models\ItemUomAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemUomAssignmentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'item_uom_assignments';
    }

    public static function permissionResource(): string
    {
        return 'item_uom_assignment';
    }

    protected static ?string $model = ItemUomAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowsUpDown;

    protected static string|null|\UnitEnum $navigationGroup = 'Inventory Setup';

    protected static ?string $modelLabel = 'Item UOM Assignment';

    protected static ?string $pluralModelLabel = 'Item UOM Assignments';

    public static function form(Schema $schema): Schema
    {
        return ItemUomAssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemUomAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemUomAssignmentsTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof ItemUomAssignment) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'item.item_code',
            'item.description',
            'uom.uom_code',
            'uom.description',
            'uom_type',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var ItemUomAssignment $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ItemUomAssignment $record */
        return [
            'Item' => $record->item
                ? "{$record->item->item_code} - {$record->item->description}"
                : '—',
            'UoM' => $record->uom
                ? "{$record->uom->uom_code} - {$record->uom->description}"
                : '—',
            'Type' => $record->uom_type_label,
            'Default' => $record->is_default ? 'Yes' : 'No',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['item', 'uom']);
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
            'index' => ListItemUomAssignments::route('/'),
            'create' => CreateItemUomAssignment::route('/create'),
            'view' => ViewItemUomAssignment::route('/{record}'),
            'edit' => EditItemUomAssignment::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(ItemUomAssignment $record): string
    {
        $itemCode = $record->item?->item_code ?: 'Item';
        $uomCode = $record->uom?->uom_code ?: 'UoM';

        return "{$itemCode} • {$uomCode} • {$record->uom_type_label}";
    }
}
