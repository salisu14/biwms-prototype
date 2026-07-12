<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentSelectionReviews\Pages;

use App\Filament\Resources\RecruitmentSelectionReviews\RecruitmentSelectionReviewResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentSelectionReview extends ViewRecord
{
    protected static string $resource = RecruitmentSelectionReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
