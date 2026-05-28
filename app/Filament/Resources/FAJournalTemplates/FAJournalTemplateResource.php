<?php

namespace App\Filament\Resources\FAJournalTemplates;

use App\Filament\Resources\FAJournalTemplates\Pages\CreateFAJournalTemplate;
use App\Filament\Resources\FAJournalTemplates\Pages\EditFAJournalTemplate;
use App\Filament\Resources\FAJournalTemplates\Pages\ListFAJournalTemplates;
use App\Filament\Resources\FAJournalTemplates\Pages\ViewFAJournalTemplate;
use App\Filament\Resources\FAJournalTemplates\Schemas\FAJournalTemplateForm;
use App\Filament\Resources\FAJournalTemplates\Schemas\FAJournalTemplateInfolist;
use App\Filament\Resources\FAJournalTemplates\Tables\FAJournalTemplatesTable;
use App\Models\FAJournalTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FAJournalTemplateResource extends Resource
{
    protected static ?string $model = FAJournalTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Finance Setup';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'FA Journal Templates';

    // Use this property to override the default URL generation
    protected static ?string $slug = 'fa-journal-templates';

    public static function form(Schema $schema): Schema
    {
        return FAJournalTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FAJournalTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FAJournalTemplatesTable::configure($table);
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
            'index' => ListFAJournalTemplates::route('/'),
            'create' => CreateFAJournalTemplate::route('/create'),
            'view' => ViewFAJournalTemplate::route('/{record}'),
            'edit' => EditFAJournalTemplate::route('/{record}/edit'),
        ];
    }
}
