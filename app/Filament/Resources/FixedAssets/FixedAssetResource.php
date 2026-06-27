<?php

namespace App\Filament\Resources\FixedAssets;

use App\Filament\Resources\FixedAssets\Pages\CreateFixedAsset;
use App\Filament\Resources\FixedAssets\Pages\EditFixedAsset;
use App\Filament\Resources\FixedAssets\Pages\ListFixedAssets;
use App\Filament\Resources\FixedAssets\Pages\ViewFixedAsset;
use App\Filament\Resources\FixedAssets\Schemas\FixedAssetForm;
use App\Filament\Resources\FixedAssets\Schemas\FixedAssetInfolist;
use App\Filament\Resources\FixedAssets\Tables\FixedAssetsTable;
use App\Models\FixedAsset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FixedAssetResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'fixed_asset';
    }

    public static function permissionResource(): string
    {
        return 'fixed_asset';
    }

    protected static ?string $model = FixedAsset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return FixedAssetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FixedAssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FixedAssetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof FixedAsset) {
            return static::getModelLabel();
        }

        $assetNumber = $record->fa_no ?: 'Unknown Asset';
        $description = $record->description ?: 'No description';

        return "{$assetNumber} - {$description}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'fa_no',
            'description',
            'description_2',
            'search_description',
            'serial_no',
            'barcode',
            'insurance_policy_no',
            'faClass.name',
            'vendor.vendor_name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var FixedAsset $record */
        return static::getRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var FixedAsset $record */
        return [
            'Class' => $record->faClass?->name ?: '—',
            'Status' => $record->status?->value ?? '—',
            'Serial No.' => $record->serial_no ?: '—',
            'Net Book Value' => number_format((float) $record->net_book_value, 2),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'faClass',
            'vendor',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFixedAssets::route('/'),
            'create' => CreateFixedAsset::route('/create'),
            'view' => ViewFixedAsset::route('/{record}'),
            'edit' => EditFixedAsset::route('/{record}/edit'),
        ];
    }
}
