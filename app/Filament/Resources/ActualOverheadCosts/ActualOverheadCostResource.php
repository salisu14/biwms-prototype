<?php

namespace App\Filament\Resources\ActualOverheadCosts;

use App\Filament\Resources\ActualOverheadCosts\Pages\CreateActualOverheadCost;
use App\Filament\Resources\ActualOverheadCosts\Pages\EditActualOverheadCost;
use App\Filament\Resources\ActualOverheadCosts\Pages\ListActualOverheadCosts;
use App\Filament\Resources\ActualOverheadCosts\Pages\ViewActualOverheadCost;
use App\Filament\Resources\ActualOverheadCosts\Schemas\ActualOverheadCostForm;
use App\Filament\Resources\ActualOverheadCosts\Schemas\ActualOverheadCostInfolist;
use App\Filament\Resources\ActualOverheadCosts\Tables\ActualOverheadCostsTable;
use App\Models\ActualOverheadCost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ActualOverheadCostResource extends Resource
{
    protected static ?string $model = ActualOverheadCost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ActualOverheadCostForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActualOverheadCostInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActualOverheadCostsTable::configure($table);
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
            'index' => ListActualOverheadCosts::route('/'),
            'create' => CreateActualOverheadCost::route('/create'),
            'view' => ViewActualOverheadCost::route('/{record}'),
            'edit' => EditActualOverheadCost::route('/{record}/edit'),
        ];
    }
}
