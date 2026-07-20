<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentSelectionReviews\Schemas;

use App\Models\RecruitmentSelectionReview;
use App\Support\Filament\RecruitmentResourceSchema;
use Filament\Schemas\Schema;

class RecruitmentSelectionReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return RecruitmentResourceSchema::infolist($schema, RecruitmentSelectionReview::class);
    }
}
