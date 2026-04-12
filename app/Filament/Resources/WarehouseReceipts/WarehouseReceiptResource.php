<?php

namespace App\Filament\Resources\WarehouseReceipts;

use App\Filament\Resources\WarehouseReceipts\Pages\CreateWarehouseReceipt;
use App\Filament\Resources\WarehouseReceipts\Pages\EditWarehouseReceipt;
use App\Filament\Resources\WarehouseReceipts\Pages\ListWarehouseReceipts;
use App\Filament\Resources\WarehouseReceipts\Pages\ViewWarehouseReceipt;
use App\Filament\Resources\WarehouseReceipts\Schemas\WarehouseReceiptForm;
use App\Filament\Resources\WarehouseReceipts\Schemas\WarehouseReceiptInfolist;
use App\Filament\Resources\WarehouseReceipts\Tables\WarehouseReceiptsTable;
use App\Models\WarehouseReceipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WarehouseReceiptResource extends Resource
{
    protected static ?string $model = WarehouseReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WarehouseReceiptForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseReceiptInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseReceiptsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseReceipts::route('/'),
            'create' => CreateWarehouseReceipt::route('/create'),
            'view' => ViewWarehouseReceipt::route('/{record}'),
            'edit' => EditWarehouseReceipt::route('/{record}/edit'),
        ];
    }
}
