<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos;

use App\Filament\Resources\PostedPurchaseCreditMemos\Pages\CreatePostedPurchaseCreditMemo;
use App\Filament\Resources\PostedPurchaseCreditMemos\Pages\EditPostedPurchaseCreditMemo;
use App\Filament\Resources\PostedPurchaseCreditMemos\Pages\ListPostedPurchaseCreditMemos;
use App\Filament\Resources\PostedPurchaseCreditMemos\Pages\ViewPostedPurchaseCreditMemo;
use App\Filament\Resources\PostedPurchaseCreditMemos\Schemas\PostedPurchaseCreditMemoForm;
use App\Filament\Resources\PostedPurchaseCreditMemos\Schemas\PostedPurchaseCreditMemoInfolist;
use App\Filament\Resources\PostedPurchaseCreditMemos\Tables\PostedPurchaseCreditMemosTable;
use App\Models\PostedPurchaseCreditMemo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PostedPurchaseCreditMemoResource extends Resource
{
    protected static ?string $model = PostedPurchaseCreditMemo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PostedPurchaseCreditMemoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PostedPurchaseCreditMemoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostedPurchaseCreditMemosTable::configure($table);
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
            'index' => ListPostedPurchaseCreditMemos::route('/'),
            'create' => CreatePostedPurchaseCreditMemo::route('/create'),
            'view' => ViewPostedPurchaseCreditMemo::route('/{record}'),
            'edit' => EditPostedPurchaseCreditMemo::route('/{record}/edit'),
        ];
    }
}
