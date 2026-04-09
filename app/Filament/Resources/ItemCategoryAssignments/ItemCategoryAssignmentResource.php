<?php

namespace App\Filament\Resources\ItemCategoryAssignments;

use App\Filament\Resources\ItemCategoryAssignments\Pages\CreateItemCategoryAssignment;
use App\Filament\Resources\ItemCategoryAssignments\Pages\EditItemCategoryAssignment;
use App\Filament\Resources\ItemCategoryAssignments\Pages\ListItemCategoryAssignments;
use App\Filament\Resources\ItemCategoryAssignments\Schemas\ItemCategoryAssignmentForm;
use App\Filament\Resources\ItemCategoryAssignments\Tables\ItemCategoryAssignmentsTable;
use App\Models\ItemCategoryAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemCategoryAssignmentResource extends Resource
{
    protected static ?string $model = ItemCategoryAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ItemCategoryAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemCategoryAssignmentsTable::configure($table);
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
            'edit' => EditItemCategoryAssignment::route('/{record}/edit'),
        ];
    }
}
