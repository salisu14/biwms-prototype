<?php

namespace App\Filament\Resources\PhysicalInventoryJournals;

use App\Filament\Resources\PhysicalInventoryJournals\Pages\CreatePhysicalInventoryJournal;
use App\Filament\Resources\PhysicalInventoryJournals\Pages\EditPhysicalInventoryJournal;
use App\Filament\Resources\PhysicalInventoryJournals\Pages\ListPhysicalInventoryJournals;
use App\Filament\Resources\PhysicalInventoryJournals\Schemas\PhysicalInventoryJournalForm;
use App\Filament\Resources\PhysicalInventoryJournals\Tables\PhysicalInventoryJournalsTable;
use App\Models\PhysicalInventoryJournal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PhysicalInventoryJournalResource extends Resource
{
    protected static ?string $model = PhysicalInventoryJournal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'journal_batch_name';

    protected static string|null|\UnitEnum $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Physical Inventory Journals';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return PhysicalInventoryJournalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PhysicalInventoryJournalsTable::configure($table);
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
            'index' => ListPhysicalInventoryJournals::route('/'),
            'create' => CreatePhysicalInventoryJournal::route('/create'),
            'edit' => EditPhysicalInventoryJournal::route('/{record}/edit'),
        ];
    }
}
