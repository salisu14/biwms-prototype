<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories;

use App\Filament\Resources\OpeningInventories\Pages\CreateOpeningInventory;
use App\Filament\Resources\OpeningInventories\Pages\EditOpeningInventory;
use App\Filament\Resources\OpeningInventories\Pages\ListOpeningInventories;
use App\Filament\Resources\OpeningInventories\Pages\ViewOpeningInventory;
use App\Filament\Resources\OpeningInventories\Schemas\OpeningInventoryForm;
use App\Filament\Resources\OpeningInventories\Schemas\OpeningInventoryInfolist;
use App\Filament\Resources\OpeningInventories\Tables\OpeningInventoriesTable;
use App\Models\OpeningInventory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OpeningInventoryResource extends Resource
{
    protected static ?string $model = OpeningInventory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArchiveBox;

    protected static string|null|\UnitEnum $navigationGroup = 'Inventory Operations';

    protected static ?string $navigationLabel = 'Opening Inventories';

    protected static ?string $modelLabel = 'Opening Inventory';

    protected static ?string $pluralModelLabel = 'Opening Inventories';

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function permissionModule(): string
    {
        return 'inventory';
    }

    public static function permissionResource(): string
    {
        return 'opening_inventory';
    }

    public static function form(Schema $schema): Schema
    {
        return OpeningInventoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OpeningInventoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpeningInventoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpeningInventories::route('/'),
            'create' => CreateOpeningInventory::route('/create'),
            'view' => ViewOpeningInventory::route('/{record}'),
            'edit' => EditOpeningInventory::route('/{record}/edit'),
        ];
    }
}
