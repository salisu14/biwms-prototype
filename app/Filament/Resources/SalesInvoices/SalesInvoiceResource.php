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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SalesInvoiceResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'sales_invoice';
    }

    protected static ?string $model = SalesInvoice::class;

    protected static ?string $recordTitleAttribute = null;

    protected static ?int $globalSearchSort = -280;

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

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof SalesInvoice) {
            return static::getModelLabel();
        }

        $customer = $record->customer?->name ?: 'Unknown Customer';

        return "{$record->invoice_number} - {$customer}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'invoice_number',
            'customer.name',
            'customer.customer_number',
            'salesOrder.order_number',
            'status',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var SalesInvoice $record */
        $customer = $record->customer?->name ?: 'Unknown Customer';

        return "{$record->invoice_number} - {$customer}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var SalesInvoice $record */
        return [
            'Customer' => $record->customer?->customer_number
                ? "{$record->customer->customer_number} - ".($record->customer?->name ?? '—')
                : ($record->customer?->name ?? '—'),
            'Sales Order' => $record->salesOrder?->order_number ?: '—',
            'Status' => $record->status?->value ?? '—',
            'Total' => number_format((float) $record->total_amount, 2).' '.($record->currency_code ?: ''),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'customer',
            'salesOrder',
        ]);
    }

    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $qualifiedInvoiceNumber = $query->qualifyColumn('invoice_number');

        $query->orderByRaw(
            "case
                when lower({$qualifiedInvoiceNumber}::text) = lower(?) then 0
                when lower({$qualifiedInvoiceNumber}::text) like lower(?) then 1
                else 2
            end",
            [$search, "%{$search}%"],
        )->orderByDesc($qualifiedInvoiceNumber);
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
