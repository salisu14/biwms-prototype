<?php

namespace App\Filament\Resources\WarehouseJournalBatches;

use App\Filament\Resources\WarehouseJournalBatches\Pages\CreateWarehouseJournalBatch;
use App\Filament\Resources\WarehouseJournalBatches\Pages\EditWarehouseJournalBatch;
use App\Filament\Resources\WarehouseJournalBatches\Pages\ListWarehouseJournalBatches;
use App\Filament\Resources\WarehouseJournalBatches\Pages\ViewWarehouseJournalBatch;
use App\Filament\Resources\WarehouseJournalBatches\RelationManagers\LinesRelationManager;
use App\Filament\Resources\WarehouseJournalBatches\Schemas\WarehouseJournalBatchForm;
use App\Filament\Resources\WarehouseJournalBatches\Schemas\WarehouseJournalBatchInfolist;
use App\Filament\Resources\WarehouseJournalBatches\Tables\WarehouseJournalBatchesTable;
use App\Models\WarehouseJournalBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WarehouseJournalBatchResource extends Resource
{
    protected static ?string $model = WarehouseJournalBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 22;

    protected static ?string $navigationLabel = 'Warehouse Journals';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return WarehouseJournalBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseJournalBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseJournalBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseJournalBatches::route('/'),
            'create' => CreateWarehouseJournalBatch::route('/create'),
            'view' => ViewWarehouseJournalBatch::route('/{record}'),
            'edit' => EditWarehouseJournalBatch::route('/{record}/edit'),
        ];
    }
}
