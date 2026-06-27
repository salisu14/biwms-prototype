<?php

namespace App\Filament\Resources\ItemTrackingCodes;

use App\Filament\Resources\ItemTrackingCodes\Pages\CreateItemTrackingCode;
use App\Filament\Resources\ItemTrackingCodes\Pages\EditItemTrackingCode;
use App\Filament\Resources\ItemTrackingCodes\Pages\ListItemTrackingCodes;
use App\Filament\Resources\ItemTrackingCodes\Pages\ViewItemTrackingCode;
use App\Filament\Resources\ItemTrackingCodes\Schemas\ItemTrackingCodeForm;
use App\Filament\Resources\ItemTrackingCodes\Schemas\ItemTrackingCodeInfolist;
use App\Filament\Resources\ItemTrackingCodes\Tables\ItemTrackingCodesTable;
use App\Models\ItemTrackingCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ItemTrackingCodeResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'item_tracking_codes';
    }

    public static function permissionResource(): string
    {
        return 'item_tracking_code';
    }

    protected static ?string $model = ItemTrackingCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'code',
            'description',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var ItemTrackingCode $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ItemTrackingCode $record */
        return [
            'Serial' => $record->snspecific_tracking ? 'Yes' : 'No',
            'Lot' => $record->lotspecific_tracking ? 'Yes' : 'No',
            'Expiration' => $record->strict_expiration_posting ? 'Strict' : 'Flexible',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery();
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof ItemTrackingCode) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ItemTrackingCodeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemTrackingCodeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemTrackingCodesTable::configure($table);
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
            'index' => ListItemTrackingCodes::route('/'),
            'create' => CreateItemTrackingCode::route('/create'),
            'view' => ViewItemTrackingCode::route('/{record}'),
            'edit' => EditItemTrackingCode::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(ItemTrackingCode $record): string
    {
        return "{$record->code} - {$record->description}";
    }
}
