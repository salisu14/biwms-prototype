<?php

namespace App\Filament\Resources\ProductionJournalTemplates;

use App\Filament\Resources\ProductionJournalTemplates\Pages\CreateProductionJournalTemplate;
use App\Filament\Resources\ProductionJournalTemplates\Pages\EditProductionJournalTemplate;
use App\Filament\Resources\ProductionJournalTemplates\Pages\ListProductionJournalTemplates;
use App\Filament\Resources\ProductionJournalTemplates\Pages\ViewProductionJournalTemplate;
use App\Filament\Resources\ProductionJournalTemplates\Schemas\ProductionJournalTemplateForm;
use App\Filament\Resources\ProductionJournalTemplates\Schemas\ProductionJournalTemplateInfolist;
use App\Filament\Resources\ProductionJournalTemplates\Tables\ProductionJournalTemplatesTable;
use App\Models\ProductionJournalTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductionJournalTemplateResource extends Resource
{
    protected static ?string $model = ProductionJournalTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Manufacturing';

    protected static ?int $navigationSort = 51;

    protected static ?string $navigationLabel = 'Production Journal Templates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductionJournalTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductionJournalTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionJournalTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionJournalTemplates::route('/'),
            'create' => CreateProductionJournalTemplate::route('/create'),
            'view' => ViewProductionJournalTemplate::route('/{record}'),
            'edit' => EditProductionJournalTemplate::route('/{record}/edit'),
        ];
    }
}
