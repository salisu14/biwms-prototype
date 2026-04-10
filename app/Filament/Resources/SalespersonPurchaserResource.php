<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalespersonPurchasers\Pages;
use App\Filament\Resources\SalespersonPurchasers\Schemas\SalespersonPurchaserForm;
use App\Filament\Resources\SalespersonPurchasers\Tables\SalespersonPurchasersTable;
use App\Models\SalespersonPurchaser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SalespersonPurchaserResource extends Resource
{
    protected static ?string $model = SalespersonPurchaser::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Administration';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Salespeople / Purchasers';

    public static function form(Schema $schema): Schema
    {
        return SalespersonPurchaserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalespersonPurchasersTable::configure($table);
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
            'index' => Pages\ListSalespersonPurchasers::route('/'),
            'create' => Pages\CreateSalespersonPurchaser::route('/create'),
            'edit' => Pages\EditSalespersonPurchaser::route('/{record}/edit'),
        ];
    }
}
