<?php

namespace App\Filament\Resources\ProductionJournalBatches;

use App\Filament\Resources\ProductionJournalBatches\Pages\CreateProductionJournalBatch;
use App\Filament\Resources\ProductionJournalBatches\Pages\EditProductionJournalBatch;
use App\Filament\Resources\ProductionJournalBatches\Pages\ListProductionJournalBatches;
use App\Filament\Resources\ProductionJournalBatches\Pages\ViewProductionJournalBatch;
use App\Filament\Resources\ProductionJournalBatches\RelationManagers\LinesRelationManager;
use App\Filament\Resources\ProductionJournalBatches\Schemas\ProductionJournalBatchForm;
use App\Filament\Resources\ProductionJournalBatches\Schemas\ProductionJournalBatchInfolist;
use App\Filament\Resources\ProductionJournalBatches\Tables\ProductionJournalBatchesTable;
use App\Models\ProductionJournalBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionJournalBatchResource extends Resource
{
    protected static ?string $model = ProductionJournalBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductionJournalBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductionJournalBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionJournalBatchesTable::configure($table);
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
            'index' => ListProductionJournalBatches::route('/'),
            'create' => CreateProductionJournalBatch::route('/create'),
            'view' => ViewProductionJournalBatch::route('/{record}'),
            'edit' => EditProductionJournalBatch::route('/{record}/edit'),
        ];
    }
}
