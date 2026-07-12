<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceProbationReviews\Schemas;

use App\Models\PerformanceProbationReview;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceProbationReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceProbationReview::class);
    }
}
