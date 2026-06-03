<?php

namespace App\Filament\Resources\Vendors;

use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Filament\Resources\Vendors\Schemas\VendorForm;
use App\Filament\Resources\Vendors\Schemas\VendorInfolist;
use App\Filament\Resources\Vendors\Tables\VendorsTable;
use App\Models\Vendor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return VendorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Vendor) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'vendor_code',
            'vendor_name',
            'email',
            'phone',
            'contact_person',
            'vendorPostingGroup.code',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Vendor $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Vendor $record */
        return [
            'Email' => $record->email ?: '—',
            'Phone' => $record->phone ?: '—',
            'Posting Group' => $record->vendorPostingGroup?->code ?: '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('vendorPostingGroup');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendors::route('/'),
            'create' => CreateVendor::route('/create'),
            'view' => ViewVendor::route('/{record}'),
            'edit' => EditVendor::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(Vendor $record): string
    {
        $vendorCode = $record->vendor_code ?: 'Unknown Vendor';
        $vendorName = $record->vendor_name ?: 'Unnamed Vendor';

        return "{$vendorCode} - {$vendorName}";
    }
}
