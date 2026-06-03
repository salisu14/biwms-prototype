<?php

namespace App\Filament\Resources\VendorInvoices;

use App\Filament\Resources\VendorInvoices\Pages\CreateVendorInvoice;
use App\Filament\Resources\VendorInvoices\Pages\EditVendorInvoice;
use App\Filament\Resources\VendorInvoices\Pages\ListVendorInvoices;
use App\Filament\Resources\VendorInvoices\Pages\ViewVendorInvoice;
use App\Filament\Resources\VendorInvoices\Schemas\VendorInvoiceForm;
use App\Filament\Resources\VendorInvoices\Schemas\VendorInvoiceInfolist;
use App\Filament\Resources\VendorInvoices\Tables\VendorInvoicesTable;
use App\Models\VendorInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VendorInvoiceResource extends Resource
{
    protected static ?string $model = VendorInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return VendorInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorInvoicesTable::configure($table);
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
            'index' => ListVendorInvoices::route('/'),
            'create' => CreateVendorInvoice::route('/create'),
            'view' => ViewVendorInvoice::route('/{record}'),
            'edit' => EditVendorInvoice::route('/{record}/edit'),
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
