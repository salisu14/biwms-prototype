<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentSelectionReviews\Tables;

use App\Models\RecruitmentSelectionReview;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class RecruitmentSelectionReviewsTable
{
    public static function configure(Table $table): Table
    {
        return RecruitmentResourceSchema::table($table, RecruitmentSelectionReview::class)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
