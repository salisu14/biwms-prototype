<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencyFrameworks;

use App\Filament\Resources\PerformanceCompetencyFrameworks\Pages\CreatePerformanceCompetencyFramework;
use App\Filament\Resources\PerformanceCompetencyFrameworks\Pages\EditPerformanceCompetencyFramework;
use App\Filament\Resources\PerformanceCompetencyFrameworks\Pages\ListPerformanceCompetencyFrameworks;
use App\Filament\Resources\PerformanceCompetencyFrameworks\Pages\ViewPerformanceCompetencyFramework;
use App\Filament\Resources\PerformanceCompetencyFrameworks\Schemas\PerformanceCompetencyFrameworkForm;
use App\Filament\Resources\PerformanceCompetencyFrameworks\Schemas\PerformanceCompetencyFrameworkInfolist;
use App\Filament\Resources\PerformanceCompetencyFrameworks\Tables\PerformanceCompetencyFrameworksTable;
use App\Models\PerformanceCompetencyFramework;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceCompetencyFrameworkResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_competency_framework';
    }

    protected static ?string $model = PerformanceCompetencyFramework::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceCompetencyFrameworkForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceCompetencyFrameworkInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceCompetencyFrameworksTable::configure($table);
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
            'index' => ListPerformanceCompetencyFrameworks::route('/'),
            'create' => CreatePerformanceCompetencyFramework::route('/create'),
            'view' => ViewPerformanceCompetencyFramework::route('/{record}'),
            'edit' => EditPerformanceCompetencyFramework::route('/{record}/edit'),
        ];
    }
}
