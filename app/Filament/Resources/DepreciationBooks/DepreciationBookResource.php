<?php

namespace App\Filament\Resources\DepreciationBooks;

use App\Filament\Resources\DepreciationBooks\Pages\CreateDepreciationBook;
use App\Filament\Resources\DepreciationBooks\Pages\EditDepreciationBook;
use App\Filament\Resources\DepreciationBooks\Pages\ListDepreciationBooks;
use App\Filament\Resources\DepreciationBooks\Pages\ViewDepreciationBook;
use App\Filament\Resources\DepreciationBooks\Schemas\DepreciationBookForm;
use App\Filament\Resources\DepreciationBooks\Schemas\DepreciationBookInfolist;
use App\Filament\Resources\DepreciationBooks\Tables\DepreciationBooksTable;
use App\Models\DepreciationBook;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DepreciationBookResource extends Resource
{
    protected static ?string $model = DepreciationBook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return DepreciationBookForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DepreciationBookInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepreciationBooksTable::configure($table);
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
            'index' => ListDepreciationBooks::route('/'),
            'create' => CreateDepreciationBook::route('/create'),
            'view' => ViewDepreciationBook::route('/{record}'),
            'edit' => EditDepreciationBook::route('/{record}/edit'),
        ];
    }
}
