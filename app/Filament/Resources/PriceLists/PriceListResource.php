<?php

namespace App\Filament\Resources\PriceLists;

use App\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Filament\Resources\PriceLists\Pages\ListPriceLists;
use App\Filament\Resources\PriceLists\Pages\ViewPriceList;
use App\Filament\Resources\PriceLists\Schemas\PriceListForm;
use App\Filament\Resources\PriceLists\Schemas\PriceListInfolist;
use App\Filament\Resources\PriceLists\Tables\PriceListsTable;
use App\Models\PriceList;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PriceListResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'pricing';
    }

    public static function permissionResource(): string
    {
        return 'price_list';
    }

    protected static ?string $model = PriceList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('edit_price_list') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_price_list') ?? false;
    }

    public function viewAny(User $user): bool
    {
        return auth()->user()?->can('view_any_price_list');
    }

    public static function form(Schema $schema): Schema
    {
        return PriceListForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PriceListInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof PriceList) {
            return static::getModelLabel();
        }

        $item = $record->item?->item_code ?? 'Unknown Item';
        $scope = $record->customer
            ? "{$record->customer->customer_number} - {$record->customer->name}"
            : ($record->customerGroup
                ? "{$record->customerGroup->code} - {$record->customerGroup->name}"
                : 'All Customers');

        return "{$item} - {$scope}";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'view' => ViewPriceList::route('/{record}'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}
