<?php

declare(strict_types=1);

namespace App\Filament\Resources\Picks;

use App\Filament\Resources\Picks\Pages\CreatePick;
use App\Filament\Resources\Picks\Pages\EditPick;
use App\Filament\Resources\Picks\Pages\ListPicks;
use App\Filament\Resources\Picks\Pages\ViewPick;
use App\Filament\Resources\Picks\Schemas\PickForm;
use App\Filament\Resources\Picks\Schemas\PickInfolist;
use App\Filament\Resources\Picks\Tables\PicksTable;
use App\Models\WarehousePick;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PickResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'picks';
    }

    public static function permissionResource(): string
    {
        return 'warehouse_pick';
    }

    protected static ?string $model = WarehousePick::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?string $navigationLabel = 'Picks';

    protected static ?string $modelLabel = 'Warehouse Pick';

    protected static ?string $pluralModelLabel = 'Warehouse Picks';

    public static function form(Schema $schema): Schema
    {
        return PickForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PickInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PicksTable::configure($table);
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
            'index' => ListPicks::route('/'),
            'create' => CreatePick::route('/create'),
            'view' => ViewPick::route('/{record}'),
            'edit' => EditPick::route('/{record}/edit'),
        ];
    }
}
