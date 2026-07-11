<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceProbationReviews\Pages;

use App\Filament\Resources\PerformanceProbationReviews\PerformanceProbationReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceProbationReviews extends ListRecords
{
    protected static string $resource = PerformanceProbationReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
