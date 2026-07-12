<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentSelectionReviews\Schemas;

use App\Models\RecruitmentSelectionReview;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentSelectionReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, RecruitmentSelectionReview::class);
    }
}
