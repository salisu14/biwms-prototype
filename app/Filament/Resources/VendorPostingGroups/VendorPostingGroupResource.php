<?php

namespace App\Filament\Resources\VendorPostingGroups;

use App\Filament\Resources\VendorPostingGroups\Pages\CreateVendorPostingGroup;
use App\Filament\Resources\VendorPostingGroups\Pages\EditVendorPostingGroup;
use App\Filament\Resources\VendorPostingGroups\Pages\ListVendorPostingGroups;
use App\Filament\Resources\VendorPostingGroups\Schemas\VendorPostingGroupForm;
use App\Filament\Resources\VendorPostingGroups\Tables\VendorPostingGroupsTable;
use App\Models\VendorPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VendorPostingGroupResource extends Resource
{
    protected static ?string $model = VendorPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return VendorPostingGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorPostingGroupsTable::configure($table);
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
            'index' => ListVendorPostingGroups::route('/'),
            'create' => CreateVendorPostingGroup::route('/create'),
            'edit' => EditVendorPostingGroup::route('/{record}/edit'),
        ];
    }
}
