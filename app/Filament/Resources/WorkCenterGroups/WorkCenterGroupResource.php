<?php

namespace App\Filament\Resources\WorkCenterGroups;

use App\Filament\Resources\WorkCenterGroups\Pages\CreateWorkCenterGroup;
use App\Filament\Resources\WorkCenterGroups\Pages\EditWorkCenterGroup;
use App\Filament\Resources\WorkCenterGroups\Pages\ListWorkCenterGroups;
use App\Filament\Resources\WorkCenterGroups\Pages\ViewWorkCenterGroup;
use App\Filament\Resources\WorkCenterGroups\Schemas\WorkCenterGroupForm;
use App\Filament\Resources\WorkCenterGroups\Schemas\WorkCenterGroupInfolist;
use App\Filament\Resources\WorkCenterGroups\Tables\WorkCenterGroupsTable;
use App\Models\Manufacturing\WorkCenterGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkCenterGroupResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'factory';
    }

    public static function permissionResource(): string
    {
        return 'work_center_group';
    }

    protected static ?string $model = WorkCenterGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return WorkCenterGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkCenterGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkCenterGroupsTable::configure($table);
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
            'index' => ListWorkCenterGroups::route('/'),
            'create' => CreateWorkCenterGroup::route('/create'),
            'view' => ViewWorkCenterGroup::route('/{record}'),
            'edit' => EditWorkCenterGroup::route('/{record}/edit'),
        ];
    }
}
