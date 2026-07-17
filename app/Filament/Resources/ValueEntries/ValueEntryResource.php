<?php

namespace App\Filament\Resources\ValueEntries;

use App\Filament\Resources\ValueEntries\Pages\ListValueEntries;
use App\Filament\Resources\ValueEntries\Pages\ViewValueEntry;
use App\Filament\Resources\ValueEntries\Schemas\ValueEntryForm;
use App\Filament\Resources\ValueEntries\Schemas\ValueEntryInfolist;
use App\Filament\Resources\ValueEntries\Tables\ValueEntriesTable;
use App\Models\ValueEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ValueEntryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'value_entry';
    }

    protected static ?string $model = ValueEntry::class;

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Accounting';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValueEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ValueEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValueEntriesTable::configure($table);
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
            'index' => ListValueEntries::route('/'),
            'view' => ViewValueEntry::route('/{record}'),
        ];
    }
}
