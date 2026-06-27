<?php

namespace App\Filament\Resources\CustomerPriceOverrides;

use App\Filament\Resources\CustomerPriceOverrides\Pages\CreateCustomerPriceOverride;
use App\Filament\Resources\CustomerPriceOverrides\Pages\EditCustomerPriceOverride;
use App\Filament\Resources\CustomerPriceOverrides\Pages\ListCustomerPriceOverrides;
use App\Filament\Resources\CustomerPriceOverrides\Pages\ViewCustomerPriceOverride;
use App\Filament\Resources\CustomerPriceOverrides\Schemas\CustomerPriceOverrideForm;
use App\Filament\Resources\CustomerPriceOverrides\Schemas\CustomerPriceOverrideInfolist;
use App\Filament\Resources\CustomerPriceOverrides\Tables\CustomerPriceOverridesTable;
use App\Models\CustomerPriceOverride;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class CustomerPriceOverrideResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'pricing';
    }

    public static function permissionResource(): string
    {
        return 'customer_price_override';
    }

    protected static ?string $model = CustomerPriceOverride::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return CustomerPriceOverrideForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerPriceOverrideInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerPriceOverridesTable::configure($table);
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
            'index' => ListCustomerPriceOverrides::route('/'),
            'create' => CreateCustomerPriceOverride::route('/create'),
            'view' => ViewCustomerPriceOverride::route('/{record}'),
            'edit' => EditCustomerPriceOverride::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof CustomerPriceOverride) {
            return static::getModelLabel();
        }

        $customer = $record->customer
            ? "{$record->customer->customer_number} - {$record->customer->name}"
            : 'Unknown Customer';

        $item = $record->item
            ? "{$record->item->item_code} - {$record->item->description}"
            : 'Unknown Item';

        return "{$customer} - {$item}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'customer.customer_number',
            'customer.name',
            'item.item_code',
            'item.description',
            'override_price',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var CustomerPriceOverride $record */
        return [
            'Customer' => $record->customer
                ? "{$record->customer->customer_number} - {$record->customer->name}"
                : '—',
            'Item' => $record->item
                ? "{$record->item->item_code} - {$record->item->description}"
                : '—',
            'Base Price' => $record->item
                ? Number::currency((float) ($record->item->unit_price ?? 0), config('app.default_currency', 'USD'))
                : '—',
            'Override Price' => Number::currency((float) $record->override_price, config('app.default_currency', 'USD')),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer', 'item']);
    }
}
