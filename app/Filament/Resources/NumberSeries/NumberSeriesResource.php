<?php

namespace App\Filament\Resources\NumberSeries;

use App\Filament\Resources\NumberSeries\Pages\CreateNumberSeries;
use App\Filament\Resources\NumberSeries\Pages\EditNumberSeries;
use App\Filament\Resources\NumberSeries\Pages\ListNumberSeries;
use App\Filament\Resources\NumberSeries\Pages\ViewNumberSeries;
use App\Filament\Resources\NumberSeries\Schemas\NumberSeriesForm;
use App\Filament\Resources\NumberSeries\Schemas\NumberSeriesInfolist;
use App\Filament\Resources\NumberSeries\Tables\NumberSeriesTable;
use App\Models\NumberSeries;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NumberSeriesResource extends Resource
{
    protected static ?string $model = NumberSeries::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return NumberSeriesForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NumberSeriesInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NumberSeriesTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()?->can('number_series.manage') ?? false);
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNumberSeries::route('/'),
            'create' => CreateNumberSeries::route('/create'),
            'view' => ViewNumberSeries::route('/{record}'),
            'edit' => EditNumberSeries::route('/{record}/edit'),
        ];
    }
}
