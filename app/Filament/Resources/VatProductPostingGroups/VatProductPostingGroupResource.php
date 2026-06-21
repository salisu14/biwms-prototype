<?php

namespace App\Filament\Resources\VatProductPostingGroups;

use App\Filament\Resources\VatProductPostingGroups\Pages\CreateVatProductPostingGroup;
use App\Filament\Resources\VatProductPostingGroups\Pages\EditVatProductPostingGroup;
use App\Filament\Resources\VatProductPostingGroups\Pages\ListVatProductPostingGroups;
use App\Filament\Resources\VatProductPostingGroups\Pages\ViewVatProductPostingGroup;
use App\Filament\Resources\VatProductPostingGroups\Schemas\VatProductPostingGroupForm;
use App\Filament\Resources\VatProductPostingGroups\Schemas\VatProductPostingGroupInfolist;
use App\Filament\Resources\VatProductPostingGroups\Tables\VatProductPostingGroupsTable;
use App\Models\VatProductPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VatProductPostingGroupResource extends Resource
{
    protected static ?string $model = VatProductPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'code';

    protected static string|UnitEnum|null $navigationGroup = 'Finance / Tax Setup';

    public static function form(Schema $schema): Schema
    {
        return VatProductPostingGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VatProductPostingGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VatProductPostingGroupsTable::configure($table);
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
            'index' => ListVatProductPostingGroups::route('/'),
            'create' => CreateVatProductPostingGroup::route('/create'),
            'view' => ViewVatProductPostingGroup::route('/{record}'),
            'edit' => EditVatProductPostingGroup::route('/{record}/edit'),
        ];
    }
}
