<?php

namespace App\Filament\Resources\FAJournalBatches;

use App\Filament\Resources\FAJournalBatches\Pages\CreateFAJournalBatch;
use App\Filament\Resources\FAJournalBatches\Pages\EditFAJournalBatch;
use App\Filament\Resources\FAJournalBatches\Pages\ListFAJournalBatches;
use App\Filament\Resources\FAJournalBatches\Pages\ViewFAJournalBatch;
use App\Filament\Resources\FAJournalBatches\Schemas\FAJournalBatchForm;
use App\Filament\Resources\FAJournalBatches\Schemas\FAJournalBatchInfolist;
use App\Filament\Resources\FAJournalBatches\Tables\FAJournalBatchesTable;
use App\Models\FAJournalBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FAJournalBatchResource extends Resource
{
    protected static ?string $model = FAJournalBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    // Use this property to override the default URL generation
    protected static ?string $slug = 'fa-journal-batches';

    public static function form(Schema $schema): Schema
    {
        return FAJournalBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FAJournalBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FAJournalBatchesTable::configure($table);
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
            'index' => ListFAJournalBatches::route('/'),
            'create' => CreateFAJournalBatch::route('/create'),
            'view' => ViewFAJournalBatch::route('/{record}'),
            'edit' => EditFAJournalBatch::route('/{record}/edit'),
        ];
    }
}
