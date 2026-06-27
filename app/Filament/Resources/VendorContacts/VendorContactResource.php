<?php

namespace App\Filament\Resources\VendorContacts;

use App\Filament\Resources\VendorContacts\Pages\CreateVendorContact;
use App\Filament\Resources\VendorContacts\Pages\EditVendorContact;
use App\Filament\Resources\VendorContacts\Pages\ListVendorContacts;
use App\Filament\Resources\VendorContacts\Pages\ViewVendorContact;
use App\Filament\Resources\VendorContacts\Schemas\ContactForm;
use App\Filament\Resources\VendorContacts\Schemas\ContactInfolist;
use App\Filament\Resources\VendorContacts\Tables\ContactsTable;
use App\Models\Contact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VendorContactResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'procurement';
    }

    public static function permissionResource(): string
    {
        return 'contact';
    }

    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Vendor Contacts';

    protected static ?string $recordTitleAttribute = null;

    protected static ?int $globalSearchSort = 120;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->vendors()
            ->with(['customer', 'vendor']);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->vendors()
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
            ?: 'Vendor Contact';
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
            ?: 'Vendor Contact';
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
            'index' => ListVendorContacts::route('/'),
            'create' => CreateVendorContact::route('/create'),
            'view' => ViewVendorContact::route('/{record}'),
            'edit' => EditVendorContact::route('/{record}/edit'),
        ];
    }
}
