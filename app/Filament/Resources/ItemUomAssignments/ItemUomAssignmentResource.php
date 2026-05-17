<?php

namespace App\Filament\Resources\ItemUomAssignments;

use App\Filament\Resources\ItemUomAssignments\Pages\CreateItemUomAssignment;
use App\Filament\Resources\ItemUomAssignments\Pages\EditItemUomAssignment;
use App\Filament\Resources\ItemUomAssignments\Pages\ListItemUomAssignments;
use App\Filament\Resources\ItemUomAssignments\Schemas\ItemUomAssignmentForm;
use App\Filament\Resources\ItemUomAssignments\Tables\ItemUomAssignmentsTable;
use App\Models\ItemUomAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemUomAssignmentResource extends Resource
{
    protected static ?string $model = ItemUomAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowsUpDown;

    protected static string|null|\UnitEnum $navigationGroup = 'Inventory Setup';

    protected static ?string $modelLabel = 'Item UOM Assignment';

    protected static ?string $pluralModelLabel = 'Item UOM Assignments';

    public static function form(Schema $schema): Schema
    {
        return ItemUomAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemUomAssignmentsTable::configure($table);
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
            'edit' => EditItemUomAssignment::route('/{record}/edit'),
        ];
    }
}
