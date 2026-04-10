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
use UnitEnum;

class VendorContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Vendor Contacts';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->vendors();
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
