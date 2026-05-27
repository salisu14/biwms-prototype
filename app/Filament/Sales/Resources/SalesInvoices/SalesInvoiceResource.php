<?php

namespace App\Filament\Sales\Resources\SalesInvoices;

use App\Filament\Resources\SalesInvoices\RelationManagers\LinesRelationManager;
use App\Filament\Resources\SalesInvoices\Schemas\SalesInvoiceForm;
use App\Filament\Resources\SalesInvoices\Schemas\SalesInvoiceInfolist;
use App\Filament\Resources\SalesInvoices\Tables\SalesInvoicesTable;
use App\Filament\Sales\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Sales\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Sales\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Sales\Resources\SalesInvoices\Pages\ViewSalesInvoice;
use App\Models\SalesInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentCurrencyDollar;

    protected static string|null|\UnitEnum $navigationGroup = 'Sales Documents';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('viewAny', SalesInvoice::class);
    }

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
            LinesRelationManager::class,
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status !== 'posted';
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status !== 'posted';
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
