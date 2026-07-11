<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceProbationReviews\Pages;

use App\Filament\Resources\PerformanceProbationReviews\PerformanceProbationReviewResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceProbationReview extends CreateRecord
{
    protected static string $resource = PerformanceProbationReviewResource::class;
}
