<?php

namespace App\Filament\Resources\SalesInvoices;

use App\Filament\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Resources\SalesInvoices\Pages\ViewPostedSalesCreditMemo;
use App\Filament\Resources\SalesInvoices\Pages\ViewPostedSalesInvoice;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    /**
     * Posted invoice history is intentionally restricted to admin/sales roles,
     * even if another role is accidentally granted generic sales invoice permissions.
     */
    public static function canAccessPostedInvoiceHistory(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (! $user->can('viewAny', SalesInvoice::class)) {
            return false;
        }

        return $user->hasAnyRole([
            'super_admin',
            'admin',
            'sales-manager',
            'sales-representative',
        ]);
    }

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

            'posted' => Pages\PostedSalesInvoices::route('/history/posted'),
            'view-posted' => ViewPostedSalesInvoice::route('/history/posted/{record}'),
            'view-posted-credit-memo' => ViewPostedSalesCreditMemo::route('/history/posted-credit-memos/{record}'),
        ];
    }
}
