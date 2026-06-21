<?php

namespace App\Filament\Resources\ItemCharges;

use App\Filament\Resources\ItemCharges\Pages\CreateItemCharge;
use App\Filament\Resources\ItemCharges\Pages\EditItemCharge;
use App\Filament\Resources\ItemCharges\Pages\ListItemCharges;
use App\Filament\Resources\ItemCharges\Pages\ViewItemCharge;
use App\Filament\Resources\ItemCharges\Schemas\ItemChargeForm;
use App\Filament\Resources\ItemCharges\Schemas\ItemChargeInfolist;
use App\Filament\Resources\ItemCharges\Tables\ItemChargesTable;
use App\Models\ItemCharge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemChargeResource extends Resource
{
    protected static ?string $model = ItemCharge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return ItemChargeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemChargeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemChargesTable::configure($table);
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
            'index' => ListItemCharges::route('/'),
            'create' => CreateItemCharge::route('/create'),
            'view' => ViewItemCharge::route('/{record}'),
            'edit' => EditItemCharge::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof ItemCharge) {
            return static::getModelLabel();
        }

        return "{$record->number} - {$record->getFullDescription()}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'number',
            'description',
            'description_2',
            'search_description',
            'gen_prod_posting_group',
            'vat_prod_posting_group',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ItemCharge $record */
        return [
            'Description' => $record->getFullDescription() ?: '—',
            'Gen. Posting Group' => $record->gen_prod_posting_group ?: '—',
            'VAT Posting Group' => $record->vat_prod_posting_group ?: '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'generalPostingGroup',
            'vatPostingGroup',
        ]);
    }
}
