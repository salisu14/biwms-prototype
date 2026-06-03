<?php

namespace App\Filament\Resources\CustomerPriceOverrides;

use App\Filament\Resources\CustomerPriceOverrides\Pages\CreateCustomerPriceOverride;
use App\Filament\Resources\CustomerPriceOverrides\Pages\EditCustomerPriceOverride;
use App\Filament\Resources\CustomerPriceOverrides\Pages\ListCustomerPriceOverrides;
use App\Filament\Resources\CustomerPriceOverrides\Schemas\CustomerPriceOverrideForm;
use App\Filament\Resources\CustomerPriceOverrides\Tables\CustomerPriceOverridesTable;
use App\Models\CustomerPriceOverride;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerPriceOverrideResource extends Resource
{
    protected static ?string $model = CustomerPriceOverride::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CustomerPriceOverrideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerPriceOverridesTable::configure($table);
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
            'index' => ListCustomerPriceOverrides::route('/'),
            'create' => CreateCustomerPriceOverride::route('/create'),
            'edit' => EditCustomerPriceOverride::route('/{record}/edit'),
        ];
    }
}
