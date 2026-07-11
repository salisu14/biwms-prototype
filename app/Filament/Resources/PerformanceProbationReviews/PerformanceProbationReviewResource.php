<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceProbationReviews;

use App\Filament\Resources\PerformanceProbationReviews\Pages\CreatePerformanceProbationReview;
use App\Filament\Resources\PerformanceProbationReviews\Pages\EditPerformanceProbationReview;
use App\Filament\Resources\PerformanceProbationReviews\Pages\ListPerformanceProbationReviews;
use App\Filament\Resources\PerformanceProbationReviews\Pages\ViewPerformanceProbationReview;
use App\Filament\Resources\PerformanceProbationReviews\Schemas\PerformanceProbationReviewForm;
use App\Filament\Resources\PerformanceProbationReviews\Schemas\PerformanceProbationReviewInfolist;
use App\Filament\Resources\PerformanceProbationReviews\Tables\PerformanceProbationReviewsTable;
use App\Models\PerformanceProbationReview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceProbationReviewResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'performance_probation_review';
    }

    protected static ?string $model = PerformanceProbationReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    public static function form(Schema $schema): Schema
    {
        return PerformanceProbationReviewForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PerformanceProbationReviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceProbationReviewsTable::configure($table);
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
            'index' => ListPerformanceProbationReviews::route('/'),
            'create' => CreatePerformanceProbationReview::route('/create'),
            'view' => ViewPerformanceProbationReview::route('/{record}'),
            'edit' => EditPerformanceProbationReview::route('/{record}/edit'),
        ];
    }
}
