<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\CustomerGroups\RelationManagers\PriceListsRelationManager;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Schemas\CustomerInfolist;
use App\Filament\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'customer';
    }

    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    protected static ?int $globalSearchSort = -260;

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PriceListsRelationManager::class,
            RelationManagers\ReferralHistoryRelationManager::class,
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Customer) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'customer_number',
            'name',
            'email',
            'phone',
            'contact.email',
            'contact.phone',
            'group.code',
            'group.name',
            'location.code',
            'location.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Customer $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Customer $record */
        return [
            'Customer' => "{$record->customer_number} - {$record->name}",
            'Email' => $record->email ?: '—',
            'Phone' => $record->phone ?: ($record->contact?->phone ?: '—'),
            'Group' => $record->group
                ? "{$record->group->code} - {$record->group->name}"
                : '—',
            'Location' => $record->location
                ? "{$record->location->code} - {$record->location->name}"
                : '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'contact',
            'group',
            'location',
        ]);
    }

    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $qualifiedCustomerNumber = $query->qualifyColumn('customer_number');
        $qualifiedEmail = $query->qualifyColumn('email');
        $qualifiedName = $query->qualifyColumn('name');

        $query->orderByRaw(
            "case
                when lower({$qualifiedCustomerNumber}::text) = lower(?) then 0
                when lower({$qualifiedEmail}::text) = lower(?) then 1
                when lower({$qualifiedName}::text) = lower(?) then 2
                when lower({$qualifiedCustomerNumber}::text) like lower(?) then 3
                when lower({$qualifiedEmail}::text) like lower(?) then 4
                when lower({$qualifiedName}::text) like lower(?) then 5
                else 6
            end",
            [$search, $search, $search, "%{$search}%", "%{$search}%", "%{$search}%"],
        )->orderBy($qualifiedName);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(Customer $record): string
    {
        $customerNumber = $record->customer_number ?: 'Unknown Customer';
        $customerName = $record->name ?: 'Unnamed Customer';

        return "{$customerNumber} - {$customerName}";
    }
}
