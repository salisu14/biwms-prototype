<?php

namespace App\Filament\Resources\WarehouseJournalTemplates;

use App\Filament\Resources\WarehouseJournalTemplates\Pages\CreateWarehouseJournalTemplate;
use App\Filament\Resources\WarehouseJournalTemplates\Pages\EditWarehouseJournalTemplate;
use App\Filament\Resources\WarehouseJournalTemplates\Pages\ListWarehouseJournalTemplates;
use App\Filament\Resources\WarehouseJournalTemplates\Schemas\WarehouseJournalTemplateForm;
use App\Filament\Resources\WarehouseJournalTemplates\Tables\WarehouseJournalTemplatesTable;
use App\Models\WarehouseJournalTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WarehouseJournalTemplateResource extends Resource
{
    protected static ?string $model = WarehouseJournalTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Finance Setup';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Warehouse J. Templates';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return WarehouseJournalTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseJournalTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseJournalTemplates::route('/'),
            'create' => CreateWarehouseJournalTemplate::route('/create'),
            'edit' => EditWarehouseJournalTemplate::route('/{record}/edit'),
        ];
    }
}
