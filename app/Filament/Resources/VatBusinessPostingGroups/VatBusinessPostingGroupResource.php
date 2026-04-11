<?php

namespace App\Filament\Resources\VatBusinessPostingGroups;

use App\Filament\Resources\VatBusinessPostingGroups\Pages\CreateVatBusinessPostingGroup;
use App\Filament\Resources\VatBusinessPostingGroups\Pages\EditVatBusinessPostingGroup;
use App\Filament\Resources\VatBusinessPostingGroups\Pages\ListVatBusinessPostingGroups;
use App\Filament\Resources\VatBusinessPostingGroups\Pages\ViewVatBusinessPostingGroup;
use App\Filament\Resources\VatBusinessPostingGroups\Schemas\VatBusinessPostingGroupForm;
use App\Filament\Resources\VatBusinessPostingGroups\Schemas\VatBusinessPostingGroupInfolist;
use App\Filament\Resources\VatBusinessPostingGroups\Tables\VatBusinessPostingGroupsTable;
use App\Models\VatBusinessPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VatBusinessPostingGroupResource extends Resource
{
    protected static ?string $model = VatBusinessPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $recordTitleAttribute = 'code';

    protected static string|UnitEnum|null $navigationGroup = 'Finance / Tax Setup';

    public static function form(Schema $schema): Schema
    {
        return VatBusinessPostingGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VatBusinessPostingGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VatBusinessPostingGroupsTable::configure($table);
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
            'index' => ListVatBusinessPostingGroups::route('/'),
            'create' => CreateVatBusinessPostingGroup::route('/create'),
            'view' => ViewVatBusinessPostingGroup::route('/{record}'),
            'edit' => EditVatBusinessPostingGroup::route('/{record}/edit'),
        ];
    }
}
