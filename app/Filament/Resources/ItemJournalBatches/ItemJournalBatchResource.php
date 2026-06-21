<?php

namespace App\Filament\Resources\ItemJournalBatches;

use App\Filament\Resources\ItemJournalBatches\Pages\CreateItemJournalBatch;
use App\Filament\Resources\ItemJournalBatches\Pages\EditItemJournalBatch;
use App\Filament\Resources\ItemJournalBatches\Pages\ListItemJournalBatches;
use App\Filament\Resources\ItemJournalBatches\Pages\ViewItemJournalBatch;
use App\Filament\Resources\ItemJournalBatches\Schemas\ItemJournalBatchForm;
use App\Filament\Resources\ItemJournalBatches\Schemas\ItemJournalBatchInfolist;
use App\Filament\Resources\ItemJournalBatches\Tables\ItemJournalBatchesTable;
use App\Models\ItemJournalBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ItemJournalBatchResource extends Resource
{
    protected static ?string $model = ItemJournalBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 25;

    protected static ?string $navigationLabel = 'Item Journals';

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return ItemJournalBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemJournalBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemJournalBatchesTable::configure($table);
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
            'index' => ListItemJournalBatches::route('/'),
            'create' => CreateItemJournalBatch::route('/create'),
            'view' => ViewItemJournalBatch::route('/{record}'),
            'edit' => EditItemJournalBatch::route('/{record}/edit'),
        ];
    }
}
