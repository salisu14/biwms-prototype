<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceProbationReviews\Pages;

use App\Filament\Resources\PerformanceProbationReviews\PerformanceProbationReviewResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceProbationReview extends ViewRecord
{
    protected static string $resource = PerformanceProbationReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
