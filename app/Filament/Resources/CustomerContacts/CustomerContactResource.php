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
use UnitEnum;

class CustomerContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Customer Contacts';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->customers();
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
            'index' => ListCustomerContacts::route('/'),
            'create' => CreateCustomerContact::route('/create'),
            'view' => ViewCustomerContact::route('/{record}'),
            'edit' => EditCustomerContact::route('/{record}/edit'),
        ];
    }
}
