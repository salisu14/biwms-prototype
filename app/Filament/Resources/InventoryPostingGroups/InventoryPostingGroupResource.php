<?php

namespace App\Filament\Resources\InventoryPostingGroups;

use App\Filament\Resources\InventoryPostingGroups\Pages\CreateInventoryPostingGroup;
use App\Filament\Resources\InventoryPostingGroups\Pages\EditInventoryPostingGroup;
use App\Filament\Resources\InventoryPostingGroups\Pages\ListInventoryPostingGroups;
use App\Filament\Resources\InventoryPostingGroups\Pages\ViewInventoryPostingGroup;
use App\Filament\Resources\InventoryPostingGroups\Schemas\InventoryPostingGroupForm;
use App\Filament\Resources\InventoryPostingGroups\Tables\InventoryPostingGroupsTable;
use App\Models\InventoryPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryPostingGroupResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'warehouse';
    }

    public static function permissionResource(): string
    {
        return 'inventory_posting_group';
    }

    protected static ?string $model = InventoryPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static ?string $recordTitleAttribute = 'description';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return InventoryPostingGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryPostingGroupsTable::configure($table);
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
            'index' => ListInventoryPostingGroups::route('/'),
            'create' => CreateInventoryPostingGroup::route('/create'),
            'view' => ViewInventoryPostingGroup::route('/{record}'),
            'edit' => EditInventoryPostingGroup::route('/{record}/edit'),
        ];
    }
}
