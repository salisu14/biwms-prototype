<?php

namespace App\Filament\Resources\GeneralJournalBatches;

use App\Filament\Resources\GeneralJournalBatches\Pages\CreateGeneralJournalBatch;
use App\Filament\Resources\GeneralJournalBatches\Pages\EditGeneralJournalBatch;
use App\Filament\Resources\GeneralJournalBatches\Pages\ListGeneralJournalBatches;
use App\Filament\Resources\GeneralJournalBatches\RelationManagers\LinesRelationManager;
use App\Filament\Resources\GeneralJournalBatches\Schemas\GeneralJournalBatchForm;
use App\Filament\Resources\GeneralJournalBatches\Schemas\GeneralJournalBatchInfolist;
use App\Filament\Resources\GeneralJournalBatches\Tables\GeneralJournalBatchesTable;
use App\Models\GeneralJournalBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GeneralJournalBatchResource extends Resource
{
    protected static ?string $model = GeneralJournalBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'General Journals';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return GeneralJournalBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GeneralJournalBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralJournalBatchesTable::configure($table);
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
            'index' => ListGeneralJournalBatches::route('/'),
            'create' => CreateGeneralJournalBatch::route('/create'),
            'view' => Pages\ViewGeneralJournalBatch::route('/{record}'),
            'edit' => EditGeneralJournalBatch::route('/{record}/edit'),
        ];
    }
}
