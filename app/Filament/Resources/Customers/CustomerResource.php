<?php

namespace App\Filament\Resources\Customers;

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
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

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
            //
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
            'group.code',
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
            'Email' => $record->email ?: '—',
            'Phone' => $record->phone ?: '—',
            'Group' => $record->group?->code ?: '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('group');
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
