<?php

namespace App\Filament\Resources\InventoryAdjustmentJournals;

use App\Filament\Resources\InventoryAdjustmentJournals\Pages\CreateInventoryAdjustmentJournal;
use App\Filament\Resources\InventoryAdjustmentJournals\Pages\EditInventoryAdjustmentJournal;
use App\Filament\Resources\InventoryAdjustmentJournals\Pages\ListInventoryAdjustmentJournals;
use App\Filament\Resources\InventoryAdjustmentJournals\Pages\ViewInventoryAdjustmentJournal;
use App\Filament\Resources\InventoryAdjustmentJournals\Schemas\InventoryAdjustmentJournalForm;
use App\Filament\Resources\InventoryAdjustmentJournals\Schemas\InventoryAdjustmentJournalInfolist;
use App\Filament\Resources\InventoryAdjustmentJournals\Tables\InventoryAdjustmentJournalsTable;
use App\Models\InventoryAdjustmentJournal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InventoryAdjustmentJournalResource extends Resource
{
    protected static ?string $model = InventoryAdjustmentJournal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $recordTitleAttribute = 'journal_batch_name';

    protected static string|null|\UnitEnum $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Inventory Adjustment Journals';

    public static function form(Schema $schema): Schema
    {
        return InventoryAdjustmentJournalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryAdjustmentJournalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryAdjustmentJournalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryAdjustmentJournals::route('/'),
            'create' => CreateInventoryAdjustmentJournal::route('/create'),
            'view' => ViewInventoryAdjustmentJournal::route('/{record}'),
            'edit' => EditInventoryAdjustmentJournal::route('/{record}/edit'),
        ];
    }
}
