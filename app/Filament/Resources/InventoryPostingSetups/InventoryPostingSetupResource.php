<?php

namespace App\Filament\Resources\InventoryPostingSetups;

use App\Filament\Resources\InventoryPostingSetups\Pages\CreateInventoryPostingSetup;
use App\Filament\Resources\InventoryPostingSetups\Pages\EditInventoryPostingSetup;
use App\Filament\Resources\InventoryPostingSetups\Pages\ListInventoryPostingSetups;
use App\Filament\Resources\InventoryPostingSetups\Pages\ViewInventoryPostingSetup;
use App\Filament\Resources\InventoryPostingSetups\Schemas\InventoryPostingSetupForm;
use App\Filament\Resources\InventoryPostingSetups\Schemas\InventoryPostingSetupInfolist;
use App\Filament\Resources\InventoryPostingSetups\Tables\InventoryPostingSetupsTable;
use App\Models\InventoryPostingSetup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InventoryPostingSetupResource extends Resource
{
    protected static ?string $model = InventoryPostingSetup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return InventoryPostingSetupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryPostingSetupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryPostingSetupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof InventoryPostingSetup) {
            return static::getModelLabel();
        }

        return static::formatRecordTitle($record);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'location.code',
            'location.name',
            'inventoryPostingGroup.code',
            'inventoryPostingGroup.description',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var InventoryPostingSetup $record */
        return static::formatRecordTitle($record);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var InventoryPostingSetup $record */
        return [
            'Inventory Account' => $record->inventoryAccount?->account_number ?: '—',
            'WIP Account' => $record->wipAccount?->account_number ?: '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'location',
            'inventoryPostingGroup',
            'inventoryAccount',
            'wipAccount',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryPostingSetups::route('/'),
            'create' => CreateInventoryPostingSetup::route('/create'),
            'view' => ViewInventoryPostingSetup::route('/{record}'),
            'edit' => EditInventoryPostingSetup::route('/{record}/edit'),
        ];
    }

    protected static function formatRecordTitle(InventoryPostingSetup $record): string
    {
        $locationCode = $record->location?->code ?: 'DEFAULT';
        $postingGroupCode = $record->inventoryPostingGroup?->code ?: 'Unknown Posting Group';

        return "{$locationCode} / {$postingGroupCode}";
    }
}
