<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales;

use App\Filament\Resources\PerformanceRatingScales\Pages\CreatePerformanceRatingScale;
use App\Filament\Resources\PerformanceRatingScales\Pages\EditPerformanceRatingScale;
use App\Filament\Resources\PerformanceRatingScales\Pages\ListPerformanceRatingScales;
use App\Filament\Resources\PerformanceRatingScales\Pages\ViewPerformanceRatingScale;
use App\Filament\Resources\PerformanceRatingScales\Schemas\PerformanceRatingScaleForm;
use App\Filament\Resources\PerformanceRatingScales\Schemas\PerformanceRatingScaleInfolist;
use App\Filament\Resources\PerformanceRatingScales\Tables\PerformanceRatingScalesTable;
use App\Models\PerformanceRatingScale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceRatingScaleResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_rating_scale';
    }

    protected static ?string $model = PerformanceRatingScale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceRatingScaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceRatingScaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceRatingScalesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LevelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPerformanceRatingScales::route('/'),
            'create' => CreatePerformanceRatingScale::route('/create'),
            'view' => ViewPerformanceRatingScale::route('/{record}'),
            'edit' => EditPerformanceRatingScale::route('/{record}/edit'),
        ];
    }
}
