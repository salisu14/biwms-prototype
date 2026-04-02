<?php

namespace App\Filament\Resources\BlanketOrders;

use App\Filament\Resources\BlanketOrders\Pages\CreateBlanketOrder;
use App\Filament\Resources\BlanketOrders\Pages\EditBlanketOrder;
use App\Filament\Resources\BlanketOrders\Pages\ListBlanketOrders;
use App\Filament\Resources\BlanketOrders\Schemas\BlanketOrderForm;
use App\Filament\Resources\BlanketOrders\Tables\BlanketOrdersTable;
use App\Models\BlanketOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BlanketOrderResource extends Resource
{
    protected static ?string $model = BlanketOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BlanketOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlanketOrdersTable::configure($table);
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
            'index' => ListBlanketOrders::route('/'),
            'create' => CreateBlanketOrder::route('/create'),
            'edit' => EditBlanketOrder::route('/{record}/edit'),
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
