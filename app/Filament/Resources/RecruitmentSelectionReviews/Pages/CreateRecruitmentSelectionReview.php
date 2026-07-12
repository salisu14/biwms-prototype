<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentSelectionReviews\Pages;

use App\Filament\Resources\RecruitmentSelectionReviews\RecruitmentSelectionReviewResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentSelectionReview extends CreateRecord
{
    protected static string $resource = RecruitmentSelectionReviewResource::class;
}
