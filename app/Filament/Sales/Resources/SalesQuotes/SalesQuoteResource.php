<?php

namespace App\Filament\Sales\Resources\SalesQuotes;

use App\Filament\Resources\SalesQuotes\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\SalesQuotes\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\SalesQuotes\Schemas\SalesQuoteForm;
use App\Filament\Resources\SalesQuotes\Schemas\SalesQuoteInfolist;
use App\Filament\Resources\SalesQuotes\Tables\SalesQuotesTable;
use App\Filament\Sales\Resources\SalesQuotes\Pages\CreateSalesQuote;
use App\Filament\Sales\Resources\SalesQuotes\Pages\EditSalesQuote;
use App\Filament\Sales\Resources\SalesQuotes\Pages\ListSalesQuotes;
use App\Filament\Sales\Resources\SalesQuotes\Pages\ViewSalesQuote;
use App\Models\SalesQuote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalesQuoteResource extends Resource
{
    protected static ?string $model = SalesQuote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|null|\UnitEnum $navigationGroup = 'Sales Documents';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('viewAny', SalesQuote::class);
    }

    public static function form(Schema $schema): Schema
    {
        return SalesQuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesQuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesQuotesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'items' => ItemsRelationManager::class,
            RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesQuotes::route('/'),
            'create' => CreateSalesQuote::route('/create'),
            'view' => ViewSalesQuote::route('/{record}'),
            'edit' => EditSalesQuote::route('/{record}/edit'),
        ];
    }
}
