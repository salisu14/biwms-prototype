<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformancePositionCompetencies;

use App\Filament\Resources\PerformancePositionCompetencies\Pages\CreatePerformancePositionCompetency;
use App\Filament\Resources\PerformancePositionCompetencies\Pages\EditPerformancePositionCompetency;
use App\Filament\Resources\PerformancePositionCompetencies\Pages\ListPerformancePositionCompetencies;
use App\Filament\Resources\PerformancePositionCompetencies\Pages\ViewPerformancePositionCompetency;
use App\Filament\Resources\PerformancePositionCompetencies\Schemas\PerformancePositionCompetencyForm;
use App\Filament\Resources\PerformancePositionCompetencies\Schemas\PerformancePositionCompetencyInfolist;
use App\Filament\Resources\PerformancePositionCompetencies\Tables\PerformancePositionCompetenciesTable;
use App\Models\PerformancePositionCompetency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformancePositionCompetencyResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_position_competency';
    }

    protected static ?string $model = PerformancePositionCompetency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformancePositionCompetencyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformancePositionCompetencyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformancePositionCompetenciesTable::configure($table);
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
            'index' => ListPerformancePositionCompetencies::route('/'),
            'create' => CreatePerformancePositionCompetency::route('/create'),
            'view' => ViewPerformancePositionCompetency::route('/{record}'),
            'edit' => EditPerformancePositionCompetency::route('/{record}/edit'),
        ];
    }
}
