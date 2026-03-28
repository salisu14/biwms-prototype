<?php

namespace App\Filament\Resources\InventoryPostingSetups;

use App\Filament\Resources\InventoryPostingSetups\Pages\CreateInventoryPostingSetup;
use App\Filament\Resources\InventoryPostingSetups\Pages\EditInventoryPostingSetup;
use App\Filament\Resources\InventoryPostingSetups\Pages\ListInventoryPostingSetups;
use App\Filament\Resources\InventoryPostingSetups\Pages\ViewInventoryPostingSetup;
use App\Filament\Resources\InventoryPostingSetups\Schemas\InventoryPostingSetupForm;
use App\Filament\Resources\InventoryPostingSetups\Schemas\InventoryPostingSetupInfolist;
use App\Filament\Resources\InventoryPostingSetups\Tables\InventoryPostingSetupsTable;
use App\Models\InventoryPostingSetup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryPostingSetupResource extends Resource
{
    protected static ?string $model = InventoryPostingSetup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return InventoryPostingSetupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryPostingSetupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryPostingSetupsTable::configure($table);
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
            'index' => ListInventoryPostingSetups::route('/'),
            'create' => CreateInventoryPostingSetup::route('/create'),
            'view' => ViewInventoryPostingSetup::route('/{record}'),
            'edit' => EditInventoryPostingSetup::route('/{record}/edit'),
        ];
    }
}
