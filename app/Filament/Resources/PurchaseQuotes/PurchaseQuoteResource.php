<?php

namespace App\Filament\Resources\PurchaseQuotes;

use App\Filament\Resources\PurchaseQuotes\Pages\CreatePurchaseQuote;
use App\Filament\Resources\PurchaseQuotes\Pages\EditPurchaseQuote;
use App\Filament\Resources\PurchaseQuotes\Pages\ListPurchaseQuotes;
use App\Filament\Resources\PurchaseQuotes\Pages\ViewPurchaseQuote;
use App\Filament\Resources\PurchaseQuotes\Schemas\PurchaseQuoteForm;
use App\Filament\Resources\PurchaseQuotes\Schemas\PurchaseQuoteInfolist;
use App\Filament\Resources\PurchaseQuotes\Tables\PurchaseQuotesTable;
use App\Models\PurchaseQuote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseQuoteResource extends Resource
{
    protected static ?string $model = PurchaseQuote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PurchaseQuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseQuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseQuotesTable::configure($table);
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
            'index' => ListPurchaseQuotes::route('/'),
            'create' => CreatePurchaseQuote::route('/create'),
            'view' => ViewPurchaseQuote::route('/{record}'),
            'edit' => EditPurchaseQuote::route('/{record}/edit'),
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
