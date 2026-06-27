<?php

namespace App\Filament\Resources\RecurringJournalBatches;

use App\Filament\Resources\RecurringJournalBatches\Pages\CreateRecurringJournalBatch;
use App\Filament\Resources\RecurringJournalBatches\Pages\EditRecurringJournalBatch;
use App\Filament\Resources\RecurringJournalBatches\Pages\ListRecurringJournalBatches;
use App\Filament\Resources\RecurringJournalBatches\Pages\ViewRecurringJournalBatch;
use App\Filament\Resources\RecurringJournalBatches\RelationManagers\LinesRelationManager;
use App\Filament\Resources\RecurringJournalBatches\Schemas\RecurringJournalBatchForm;
use App\Filament\Resources\RecurringJournalBatches\Schemas\RecurringJournalBatchInfolist;
use App\Filament\Resources\RecurringJournalBatches\Tables\RecurringJournalBatchesTable;
use App\Models\RecurringJournalBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RecurringJournalBatchResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'recurring_journal_batch';
    }

    protected static ?string $model = RecurringJournalBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 22;

    protected static ?string $navigationLabel = 'Recurring Journals';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return RecurringJournalBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecurringJournalBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringJournalBatchesTable::configure($table);
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
            'index' => ListRecurringJournalBatches::route('/'),
            'create' => CreateRecurringJournalBatch::route('/create'),
            'view' => ViewRecurringJournalBatch::route('/{record}'),
            'edit' => EditRecurringJournalBatch::route('/{record}/edit'),
        ];
    }
}
