<?php

namespace App\Filament\Resources\VatPostingSetups;

use App\Filament\Resources\VatPostingSetups\Pages\CreateVatPostingSetup;
use App\Filament\Resources\VatPostingSetups\Pages\EditVatPostingSetup;
use App\Filament\Resources\VatPostingSetups\Pages\ListVatPostingSetups;
use App\Filament\Resources\VatPostingSetups\Pages\ViewVatPostingSetup;
use App\Filament\Resources\VatPostingSetups\Schemas\VatPostingSetupForm;
use App\Filament\Resources\VatPostingSetups\Schemas\VatPostingSetupInfolist;
use App\Filament\Resources\VatPostingSetups\Tables\VatPostingSetupsTable;
use App\Models\VatPostingSetup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VatPostingSetupResource extends Resource
{
    protected static ?string $model = VatPostingSetup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Finance / Tax Setup';

    public static function form(Schema $schema): Schema
    {
        return VatPostingSetupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VatPostingSetupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VatPostingSetupsTable::configure($table);
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
            'index' => ListVatPostingSetups::route('/'),
            'create' => CreateVatPostingSetup::route('/create'),
            'view' => ViewVatPostingSetup::route('/{record}'),
            'edit' => EditVatPostingSetup::route('/{record}/edit'),
        ];
    }
}
