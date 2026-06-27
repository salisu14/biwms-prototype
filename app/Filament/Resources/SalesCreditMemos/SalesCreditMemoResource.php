<?php

namespace App\Filament\Resources\SalesCreditMemos;

use App\Filament\Resources\SalesCreditMemos\Pages\CreateSalesCreditMemo;
use App\Filament\Resources\SalesCreditMemos\Pages\EditSalesCreditMemo;
use App\Filament\Resources\SalesCreditMemos\Pages\ListSalesCreditMemos;
use App\Filament\Resources\SalesCreditMemos\Pages\ViewSalesCreditMemo;
use App\Filament\Resources\SalesCreditMemos\Schemas\SalesCreditMemoForm;
use App\Filament\Resources\SalesCreditMemos\Schemas\SalesCreditMemoInfolist;
use App\Filament\Resources\SalesCreditMemos\Tables\SalesCreditMemosTable;
use App\Models\SalesCreditMemo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalesCreditMemoResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'sales_credit_memo';
    }

    protected static ?string $model = SalesCreditMemo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SalesCreditMemoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesCreditMemoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesCreditMemosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesCreditMemos::route('/'),
            'create' => CreateSalesCreditMemo::route('/create'),
            'view' => ViewSalesCreditMemo::route('/{record}'),
            'edit' => EditSalesCreditMemo::route('/{record}/edit'),
        ];
    }
}
