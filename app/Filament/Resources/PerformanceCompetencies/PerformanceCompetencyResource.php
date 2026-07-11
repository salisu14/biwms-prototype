<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencies;

use App\Filament\Resources\PerformanceCompetencies\Pages\CreatePerformanceCompetency;
use App\Filament\Resources\PerformanceCompetencies\Pages\EditPerformanceCompetency;
use App\Filament\Resources\PerformanceCompetencies\Pages\ListPerformanceCompetencies;
use App\Filament\Resources\PerformanceCompetencies\Pages\ViewPerformanceCompetency;
use App\Filament\Resources\PerformanceCompetencies\Schemas\PerformanceCompetencyForm;
use App\Filament\Resources\PerformanceCompetencies\Schemas\PerformanceCompetencyInfolist;
use App\Filament\Resources\PerformanceCompetencies\Tables\PerformanceCompetenciesTable;
use App\Models\PerformanceCompetency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceCompetencyResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_competency';
    }

    protected static ?string $model = PerformanceCompetency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceCompetencyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceCompetencyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceCompetenciesTable::configure($table);
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
            'index' => ListPerformanceCompetencies::route('/'),
            'create' => CreatePerformanceCompetency::route('/create'),
            'view' => ViewPerformanceCompetency::route('/{record}'),
            'edit' => EditPerformanceCompetency::route('/{record}/edit'),
        ];
    }
}
