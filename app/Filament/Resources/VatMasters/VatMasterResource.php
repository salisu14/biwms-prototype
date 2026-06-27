<?php

namespace App\Filament\Resources\VatMasters;

use App\Filament\Resources\VatMasters\Pages\CreateVatMaster;
use App\Filament\Resources\VatMasters\Pages\EditVatMaster;
use App\Filament\Resources\VatMasters\Pages\ListVatMasters;
use App\Filament\Resources\VatMasters\Pages\ViewVatMaster;
use App\Filament\Resources\VatMasters\Schemas\VatMasterForm;
use App\Filament\Resources\VatMasters\Schemas\VatMasterInfolist;
use App\Filament\Resources\VatMasters\Tables\VatMastersTable;
use App\Models\VatMaster;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VatMasterResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'vat_master';
    }

    protected static ?string $model = VatMaster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return VatMasterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VatMasterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VatMastersTable::configure($table);
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
            'index' => ListVatMasters::route('/'),
            'create' => CreateVatMaster::route('/create'),
            'view' => ViewVatMaster::route('/{record}'),
            'edit' => EditVatMaster::route('/{record}/edit'),
        ];
    }
}
