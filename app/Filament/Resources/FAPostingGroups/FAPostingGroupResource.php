<?php

namespace App\Filament\Resources\FAPostingGroups;

use App\Filament\Resources\FAPostingGroups\Pages\CreateFAPostingGroup;
use App\Filament\Resources\FAPostingGroups\Pages\EditFAPostingGroup;
use App\Filament\Resources\FAPostingGroups\Pages\ListFAPostingGroups;
use App\Filament\Resources\FAPostingGroups\Pages\ViewFAPostingGroup;
use App\Filament\Resources\FAPostingGroups\Schemas\FAPostingGroupForm;
use App\Filament\Resources\FAPostingGroups\Schemas\FAPostingGroupInfolist;
use App\Filament\Resources\FAPostingGroups\Tables\FAPostingGroupsTable;
use App\Models\FAPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FAPostingGroupResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'fixed_asset';
    }

    public static function permissionResource(): string
    {
        return 'f_a_posting_group';
    }

    protected static ?string $model = FAPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return FAPostingGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FAPostingGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FAPostingGroupsTable::configure($table);
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
            'index' => ListFAPostingGroups::route('/'),
            'create' => CreateFAPostingGroup::route('/create'),
            'view' => ViewFAPostingGroup::route('/{record}'),
            'edit' => EditFAPostingGroup::route('/{record}/edit'),
        ];
    }
}
