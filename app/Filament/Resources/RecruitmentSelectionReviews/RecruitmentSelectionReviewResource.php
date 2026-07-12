<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentSelectionReviews;

use App\Filament\Resources\RecruitmentSelectionReviews\Pages\CreateRecruitmentSelectionReview;
use App\Filament\Resources\RecruitmentSelectionReviews\Pages\EditRecruitmentSelectionReview;
use App\Filament\Resources\RecruitmentSelectionReviews\Pages\ListRecruitmentSelectionReviews;
use App\Filament\Resources\RecruitmentSelectionReviews\Pages\ViewRecruitmentSelectionReview;
use App\Filament\Resources\RecruitmentSelectionReviews\Schemas\RecruitmentSelectionReviewForm;
use App\Filament\Resources\RecruitmentSelectionReviews\Schemas\RecruitmentSelectionReviewInfolist;
use App\Filament\Resources\RecruitmentSelectionReviews\Tables\RecruitmentSelectionReviewsTable;
use App\Models\RecruitmentSelectionReview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecruitmentSelectionReviewResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'recruitment_selection_review';
    }

    protected static ?string $model = RecruitmentSelectionReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment & Onboarding';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentSelectionReviewForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecruitmentSelectionReviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentSelectionReviewsTable::configure($table);
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
            'index' => ListRecruitmentSelectionReviews::route('/'),
            'create' => CreateRecruitmentSelectionReview::route('/create'),
            'view' => ViewRecruitmentSelectionReview::route('/{record}'),
            'edit' => EditRecruitmentSelectionReview::route('/{record}/edit'),
        ];
    }
}
