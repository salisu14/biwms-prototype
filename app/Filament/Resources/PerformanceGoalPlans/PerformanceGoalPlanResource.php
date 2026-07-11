<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoalPlans;

use App\Filament\Resources\PerformanceGoalPlans\Pages\CreatePerformanceGoalPlan;
use App\Filament\Resources\PerformanceGoalPlans\Pages\EditPerformanceGoalPlan;
use App\Filament\Resources\PerformanceGoalPlans\Pages\ListPerformanceGoalPlans;
use App\Filament\Resources\PerformanceGoalPlans\Pages\ViewPerformanceGoalPlan;
use App\Filament\Resources\PerformanceGoalPlans\Schemas\PerformanceGoalPlanForm;
use App\Filament\Resources\PerformanceGoalPlans\Schemas\PerformanceGoalPlanInfolist;
use App\Filament\Resources\PerformanceGoalPlans\Tables\PerformanceGoalPlansTable;
use App\Models\PerformanceGoalPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceGoalPlanResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_goal_plan';
    }

    protected static ?string $model = PerformanceGoalPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceGoalPlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceGoalPlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceGoalPlansTable::configure($table);
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
            'index' => ListPerformanceGoalPlans::route('/'),
            'create' => CreatePerformanceGoalPlan::route('/create'),
            'view' => ViewPerformanceGoalPlan::route('/{record}'),
            'edit' => EditPerformanceGoalPlan::route('/{record}/edit'),
        ];
    }
}
