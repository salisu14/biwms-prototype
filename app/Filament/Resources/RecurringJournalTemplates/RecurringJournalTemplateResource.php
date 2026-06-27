<?php

namespace App\Filament\Resources\RecurringJournalTemplates;

use App\Filament\Resources\RecurringJournalTemplates\Pages\CreateRecurringJournalTemplate;
use App\Filament\Resources\RecurringJournalTemplates\Pages\EditRecurringJournalTemplate;
use App\Filament\Resources\RecurringJournalTemplates\Pages\ListRecurringJournalTemplates;
use App\Filament\Resources\RecurringJournalTemplates\Schemas\RecurringJournalTemplateForm;
use App\Filament\Resources\RecurringJournalTemplates\Tables\RecurringJournalTemplatesTable;
use App\Models\RecurringJournalTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RecurringJournalTemplateResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'recurring_journal_template';
    }

    protected static ?string $model = RecurringJournalTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'Finance Setup';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Recurring J. Templates';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return RecurringJournalTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringJournalTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringJournalTemplates::route('/'),
            'create' => CreateRecurringJournalTemplate::route('/create'),
            'edit' => EditRecurringJournalTemplate::route('/{record}/edit'),
        ];
    }
}
