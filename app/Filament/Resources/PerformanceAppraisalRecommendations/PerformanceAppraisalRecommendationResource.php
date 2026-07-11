<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalRecommendations;

use App\Filament\Resources\PerformanceAppraisalRecommendations\Pages\CreatePerformanceAppraisalRecommendation;
use App\Filament\Resources\PerformanceAppraisalRecommendations\Pages\EditPerformanceAppraisalRecommendation;
use App\Filament\Resources\PerformanceAppraisalRecommendations\Pages\ListPerformanceAppraisalRecommendations;
use App\Filament\Resources\PerformanceAppraisalRecommendations\Pages\ViewPerformanceAppraisalRecommendation;
use App\Filament\Resources\PerformanceAppraisalRecommendations\Schemas\PerformanceAppraisalRecommendationForm;
use App\Filament\Resources\PerformanceAppraisalRecommendations\Schemas\PerformanceAppraisalRecommendationInfolist;
use App\Filament\Resources\PerformanceAppraisalRecommendations\Tables\PerformanceAppraisalRecommendationsTable;
use App\Models\PerformanceAppraisalRecommendation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceAppraisalRecommendationResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_recommendation';
    }

    protected static ?string $model = PerformanceAppraisalRecommendation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceAppraisalRecommendationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceAppraisalRecommendationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceAppraisalRecommendationsTable::configure($table);
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
            'index' => ListPerformanceAppraisalRecommendations::route('/'),
            'create' => CreatePerformanceAppraisalRecommendation::route('/create'),
            'view' => ViewPerformanceAppraisalRecommendation::route('/{record}'),
            'edit' => EditPerformanceAppraisalRecommendation::route('/{record}/edit'),
        ];
    }
}
