<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceProbationReviews\Schemas;

use App\Models\PerformanceProbationReview;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceProbationReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, PerformanceProbationReview::class);
    }
}
