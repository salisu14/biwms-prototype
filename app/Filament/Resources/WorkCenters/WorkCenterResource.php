<?php

namespace App\Filament\Resources\WorkCenters;

use App\Filament\Resources\WorkCenters\Pages\CreateWorkCenter;
use App\Filament\Resources\WorkCenters\Pages\EditWorkCenter;
use App\Filament\Resources\WorkCenters\Pages\ListWorkCenters;
use App\Filament\Resources\WorkCenters\Pages\ViewWorkCenter;
use App\Filament\Resources\WorkCenters\Schemas\WorkCenterForm;
use App\Filament\Resources\WorkCenters\Schemas\WorkCenterInfolist;
use App\Filament\Resources\WorkCenters\Tables\WorkCentersTable;
use App\Models\Manufacturing\WorkCenter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkCenterResource extends Resource
{
    protected static ?string $model = WorkCenter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return WorkCenterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkCenterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkCentersTable::configure($table);
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
            'index' => ListWorkCenters::route('/'),
            'create' => CreateWorkCenter::route('/create'),
            'view' => ViewWorkCenter::route('/{record}'),
            'edit' => EditWorkCenter::route('/{record}/edit'),
        ];
    }
}
