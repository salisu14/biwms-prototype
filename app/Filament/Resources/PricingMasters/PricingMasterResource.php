<?php

namespace App\Filament\Resources\PricingMasters;

use App\Filament\Resources\PricingMasters\Pages\CreatePricingMaster;
use App\Filament\Resources\PricingMasters\Pages\EditPricingMaster;
use App\Filament\Resources\PricingMasters\Pages\ListPricingMasters;
use App\Filament\Resources\PricingMasters\Pages\ViewPricingMaster;
use App\Filament\Resources\PricingMasters\Schemas\PricingMasterForm;
use App\Filament\Resources\PricingMasters\Schemas\PricingMasterInfolist;
use App\Filament\Resources\PricingMasters\Tables\PricingMastersTable;
use App\Models\PricingMaster;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PricingMasterResource extends Resource
{
    protected static ?string $model = PricingMaster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return PricingMasterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PricingMasterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PricingMastersTable::configure($table);
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
            'index' => ListPricingMasters::route('/'),
            'create' => CreatePricingMaster::route('/create'),
            'view' => ViewPricingMaster::route('/{record}'),
            'edit' => EditPricingMaster::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
