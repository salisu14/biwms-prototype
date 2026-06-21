<?php

namespace App\Filament\Resources\GeneralJournalTemplates;

use App\Filament\Resources\GeneralJournalTemplates\Pages\CreateGeneralJournalTemplate;
use App\Filament\Resources\GeneralJournalTemplates\Pages\EditGeneralJournalTemplate;
use App\Filament\Resources\GeneralJournalTemplates\Pages\ListGeneralJournalTemplates;
use App\Filament\Resources\GeneralJournalTemplates\Schemas\GeneralJournalTemplateForm;
use App\Filament\Resources\GeneralJournalTemplates\Tables\GeneralJournalTemplatesTable;
use App\Models\GeneralJournalTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GeneralJournalTemplateResource extends Resource
{
    protected static ?string $model = GeneralJournalTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Finance Setup';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Journal Templates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return GeneralJournalTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralJournalTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeneralJournalTemplates::route('/'),
            'create' => CreateGeneralJournalTemplate::route('/create'),
            'edit' => EditGeneralJournalTemplate::route('/{record}/edit'),
        ];
    }
}
