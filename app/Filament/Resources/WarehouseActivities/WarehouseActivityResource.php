<?php

namespace App\Filament\Resources\WarehouseActivities;

use App\Filament\Resources\WarehouseActivities\Pages\CreateWarehouseActivity;
use App\Filament\Resources\WarehouseActivities\Pages\EditWarehouseActivity;
use App\Filament\Resources\WarehouseActivities\Pages\ListWarehouseActivities;
use App\Filament\Resources\WarehouseActivities\Pages\ViewWarehouseActivity;
use App\Filament\Resources\WarehouseActivities\Schemas\WarehouseActivityForm;
use App\Filament\Resources\WarehouseActivities\Schemas\WarehouseActivityInfolist;
use App\Filament\Resources\WarehouseActivities\Tables\WarehouseActivitiesTable;
use App\Models\WarehouseActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WarehouseActivityResource extends Resource
{
    protected static ?string $model = WarehouseActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WarehouseActivityForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseActivitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseActivities::route('/'),
            'create' => CreateWarehouseActivity::route('/create'),
            'view' => ViewWarehouseActivity::route('/{record}'),
            'edit' => EditWarehouseActivity::route('/{record}/edit'),
        ];
    }
}
