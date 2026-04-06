<?php

namespace App\Filament\Resources\SalesInvoices;

use App\Filament\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Resources\SalesInvoices\Pages\ViewSalesInvoice;
use App\Filament\Resources\SalesInvoices\Schemas\SalesInvoiceForm;
use App\Filament\Resources\SalesInvoices\Schemas\SalesInvoiceInfolist;
use App\Filament\Resources\SalesInvoices\Tables\SalesInvoicesTable;
use App\Models\SalesInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SalesInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesInvoicesTable::configure($table);
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
            'index' => ListSalesInvoices::route('/'),
            'create' => CreateSalesInvoice::route('/create'),
            'view' => ViewSalesInvoice::route('/{record}'),
            'edit' => EditSalesInvoice::route('/{record}/edit'),
        ];
    }
}
