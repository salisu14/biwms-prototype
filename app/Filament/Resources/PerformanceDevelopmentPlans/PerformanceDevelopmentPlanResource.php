<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceDevelopmentPlans;

use App\Filament\Resources\PerformanceDevelopmentPlans\Pages\CreatePerformanceDevelopmentPlan;
use App\Filament\Resources\PerformanceDevelopmentPlans\Pages\EditPerformanceDevelopmentPlan;
use App\Filament\Resources\PerformanceDevelopmentPlans\Pages\ListPerformanceDevelopmentPlans;
use App\Filament\Resources\PerformanceDevelopmentPlans\Pages\ViewPerformanceDevelopmentPlan;
use App\Filament\Resources\PerformanceDevelopmentPlans\Schemas\PerformanceDevelopmentPlanForm;
use App\Filament\Resources\PerformanceDevelopmentPlans\Schemas\PerformanceDevelopmentPlanInfolist;
use App\Filament\Resources\PerformanceDevelopmentPlans\Tables\PerformanceDevelopmentPlansTable;
use App\Models\PerformanceDevelopmentPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceDevelopmentPlanResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_development_plan';
    }

    protected static ?string $model = PerformanceDevelopmentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceDevelopmentPlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceDevelopmentPlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceDevelopmentPlansTable::configure($table);
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
            'index' => ListPerformanceDevelopmentPlans::route('/'),
            'create' => CreatePerformanceDevelopmentPlan::route('/create'),
            'view' => ViewPerformanceDevelopmentPlan::route('/{record}'),
            'edit' => EditPerformanceDevelopmentPlan::route('/{record}/edit'),
        ];
    }
}
