<?php

namespace App\Filament\Resources\TaxTable;

use App\Filament\Resources\TaxTable\Pages\CreateTaxTable;
use App\Filament\Resources\TaxTable\Pages\EditTaxTable;
use App\Filament\Resources\TaxTable\Pages\ListTaxTables;
use App\Filament\Resources\TaxTable\Schemas\TaxTableForm;
use App\Filament\Resources\TaxTable\Tables\TaxTablesTable;
use App\Models\TaxTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TaxTableResource extends Resource
{
    protected static ?string $model = TaxTable::class;

    protected static ?string $slug = 'tax-tables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TaxTableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxTablesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BracketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxTables::route('/'),
            'create' => CreateTaxTable::route('/create'),
            'edit' => EditTaxTable::route('/{record}/edit'),
        ];
    }
}
