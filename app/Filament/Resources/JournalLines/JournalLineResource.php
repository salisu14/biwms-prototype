<?php

namespace App\Filament\Resources\JournalLines;

use App\Filament\Resources\JournalLines\Pages\CreateJournalLine;
use App\Filament\Resources\JournalLines\Pages\EditJournalLine;
use App\Filament\Resources\JournalLines\Pages\ListJournalLines;
use App\Filament\Resources\JournalLines\Pages\ViewJournalLine;
use App\Filament\Resources\JournalLines\Schemas\JournalLineForm;
use App\Filament\Resources\JournalLines\Schemas\JournalLineInfolist;
use App\Filament\Resources\JournalLines\Tables\JournalLinesTable;
use App\Models\JournalLine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class JournalLineResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'journal_line';
    }

    protected static ?string $model = JournalLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Journal Lines';

    public static function form(Schema $schema): Schema
    {
        return JournalLineForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return JournalLineInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JournalLinesTable::configure($table);
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
            'index' => ListJournalLines::route('/'),
            'create' => CreateJournalLine::route('/create'),
            'view' => ViewJournalLine::route('/{record}'),
            'edit' => EditJournalLine::route('/{record}/edit'),
        ];
    }
}
