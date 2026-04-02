<?php

namespace App\Filament\Resources\CustomerPostingGroups;

use App\Filament\Resources\CustomerPostingGroups\Pages\CreateCustomerPostingGroup;
use App\Filament\Resources\CustomerPostingGroups\Pages\EditCustomerPostingGroup;
use App\Filament\Resources\CustomerPostingGroups\Pages\ListCustomerPostingGroups;
use App\Filament\Resources\CustomerPostingGroups\Pages\ViewCustomerPostingGroup;
use App\Filament\Resources\CustomerPostingGroups\Schemas\CustomerPostingGroupForm;
use App\Filament\Resources\CustomerPostingGroups\Schemas\CustomerPostingGroupInfolist;
use App\Filament\Resources\CustomerPostingGroups\Tables\CustomerPostingGroupsTable;
use App\Models\CustomerPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerPostingGroupResource extends Resource
{
    protected static ?string $model = CustomerPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return CustomerPostingGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerPostingGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerPostingGroupsTable::configure($table);
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
            'index' => ListCustomerPostingGroups::route('/'),
            'create' => CreateCustomerPostingGroup::route('/create'),
            'view' => ViewCustomerPostingGroup::route('/{record}'),
            'edit' => EditCustomerPostingGroup::route('/{record}/edit'),
        ];
    }
}
