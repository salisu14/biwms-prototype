<?php

namespace App\Filament\Resources\CustomerContacts;

use App\Filament\Resources\CustomerContacts\Pages\CreateCustomerContact;
use App\Filament\Resources\CustomerContacts\Pages\EditCustomerContact;
use App\Filament\Resources\CustomerContacts\Pages\ListCustomerContacts;
use App\Filament\Resources\CustomerContacts\Pages\ViewCustomerContact;
use App\Filament\Resources\CustomerContacts\Schemas\ContactForm;
use App\Filament\Resources\CustomerContacts\Schemas\ContactInfolist;
use App\Filament\Resources\CustomerContacts\Tables\ContactsTable;
use App\Models\Contact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CustomerContactResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'sales';
    }

    public static function permissionResource(): string
    {
        return 'contact';
    }

    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Customer Contacts';

    protected static ?string $recordTitleAttribute = null;

    protected static ?int $globalSearchSort = 120;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->customers()
            ->with(['customer', 'vendor']);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->customers()
            ->with(['customer', 'vendor']);
    }

    public static function form(Schema $schema): Schema
    {
        return ContactForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContactInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactsTable::configure($table);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Contact) {
            return static::getModelLabel();
        }

        return $record->full_name
            ?: $record->name
            ?: $record->company_name
            ?: 'Customer Contact';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'full_name',
            'company_name',
            'email',
            'phone',
            'mobile',
            'city',
            'country',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Contact $record */
        return $record->full_name
            ?: $record->name
            ?: $record->company_name
            ?: 'Customer Contact';
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Contact $record */
        return [
            'Type' => $record->type?->label() ?? '—',
            'Role' => $record->role?->label() ?? '—',
            'Email' => $record->email ?? '—',
            'Phone' => $record->phone ?? '—',
            'Location' => trim(implode(', ', array_filter([
                $record->city,
                $record->country,
            ]))) ?: '—',
        ];
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
            'index' => ListCustomerContacts::route('/'),
            'create' => CreateCustomerContact::route('/create'),
            'view' => ViewCustomerContact::route('/{record}'),
            'edit' => EditCustomerContact::route('/{record}/edit'),
        ];
    }
}
