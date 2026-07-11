<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceImprovementPlans;

use App\Filament\Resources\PerformanceImprovementPlans\Pages\CreatePerformanceImprovementPlan;
use App\Filament\Resources\PerformanceImprovementPlans\Pages\EditPerformanceImprovementPlan;
use App\Filament\Resources\PerformanceImprovementPlans\Pages\ListPerformanceImprovementPlans;
use App\Filament\Resources\PerformanceImprovementPlans\Pages\ViewPerformanceImprovementPlan;
use App\Filament\Resources\PerformanceImprovementPlans\Schemas\PerformanceImprovementPlanForm;
use App\Filament\Resources\PerformanceImprovementPlans\Schemas\PerformanceImprovementPlanInfolist;
use App\Filament\Resources\PerformanceImprovementPlans\Tables\PerformanceImprovementPlansTable;
use App\Models\PerformanceImprovementPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceImprovementPlanResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_improvement_plan';
    }

    protected static ?string $model = PerformanceImprovementPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceImprovementPlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceImprovementPlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceImprovementPlansTable::configure($table);
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
            'index' => ListPerformanceImprovementPlans::route('/'),
            'create' => CreatePerformanceImprovementPlan::route('/create'),
            'view' => ViewPerformanceImprovementPlan::route('/{record}'),
            'edit' => EditPerformanceImprovementPlan::route('/{record}/edit'),
        ];
    }
}
