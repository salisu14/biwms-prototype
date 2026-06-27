<?php

namespace App\Filament\Resources\OverheadCostCategories;

use App\Filament\Resources\OverheadCostCategories\Pages\CreateOverheadCostCategory;
use App\Filament\Resources\OverheadCostCategories\Pages\EditOverheadCostCategory;
use App\Filament\Resources\OverheadCostCategories\Pages\ListOverheadCostCategories;
use App\Filament\Resources\OverheadCostCategories\Schemas\OverheadCostCategoryForm;
use App\Filament\Resources\OverheadCostCategories\Tables\OverheadCostCategoriesTable;
use App\Models\OverheadCostCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OverheadCostCategoryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'factory';
    }

    public static function permissionResource(): string
    {
        return 'overhead_cost_category';
    }

    protected static ?string $model = OverheadCostCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return OverheadCostCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OverheadCostCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActualOverheadCostsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOverheadCostCategories::route('/'),
            'create' => CreateOverheadCostCategory::route('/create'),
            'edit' => EditOverheadCostCategory::route('/{record}/edit'),
        ];
    }
}
