<?php

namespace App\Filament\Resources\SalesQuoteRevisions;

use App\Filament\Resources\SalesQuoteRevisions\Pages\CreateSalesQuoteRevision;
use App\Filament\Resources\SalesQuoteRevisions\Pages\EditSalesQuoteRevision;
use App\Filament\Resources\SalesQuoteRevisions\Pages\ListSalesQuoteRevisions;
use App\Filament\Resources\SalesQuoteRevisions\Pages\ViewSalesQuoteRevision;
use App\Filament\Resources\SalesQuoteRevisions\Schemas\SalesQuoteRevisionForm;
use App\Filament\Resources\SalesQuoteRevisions\Schemas\SalesQuoteRevisionInfolist;
use App\Filament\Resources\SalesQuoteRevisions\Tables\SalesQuoteRevisionsTable;
use App\Models\SalesQuoteRevision;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalesQuoteRevisionResource extends Resource
{
    protected static ?string $model = SalesQuoteRevision::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SalesQuoteRevisionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesQuoteRevisionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesQuoteRevisionsTable::configure($table);
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
            'index' => ListSalesQuoteRevisions::route('/'),
            'create' => CreateSalesQuoteRevision::route('/create'),
            'view' => ViewSalesQuoteRevision::route('/{record}'),
            'edit' => EditSalesQuoteRevision::route('/{record}/edit'),
        ];
    }
}
