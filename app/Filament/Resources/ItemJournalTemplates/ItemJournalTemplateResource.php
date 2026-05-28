<?php

namespace App\Filament\Resources\ItemJournalTemplates;

use App\Filament\Resources\ItemJournalTemplates\Pages\CreateItemJournalTemplate;
use App\Filament\Resources\ItemJournalTemplates\Pages\EditItemJournalTemplate;
use App\Filament\Resources\ItemJournalTemplates\Pages\ListItemJournalTemplates;
use App\Filament\Resources\ItemJournalTemplates\Pages\ViewItemJournalTemplate;
use App\Filament\Resources\ItemJournalTemplates\Schemas\ItemJournalTemplateForm;
use App\Filament\Resources\ItemJournalTemplates\Schemas\ItemJournalTemplateInfolist;
use App\Filament\Resources\ItemJournalTemplates\Tables\ItemJournalTemplatesTable;
use App\Models\ItemJournalTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemJournalTemplateResource extends Resource
{
    protected static ?string $model = ItemJournalTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $recordTitleAttribute = 'description';

    protected static string|null|\UnitEnum $navigationGroup = 'Finance Setup';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Item Journal Templates';

    public static function form(Schema $schema): Schema
    {
        return ItemJournalTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemJournalTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemJournalTemplatesTable::configure($table);
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
            'index' => ListItemJournalTemplates::route('/'),
            'create' => CreateItemJournalTemplate::route('/create'),
            'view' => ViewItemJournalTemplate::route('/{record}'),
            'edit' => EditItemJournalTemplate::route('/{record}/edit'),
        ];
    }
}
