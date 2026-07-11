<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceProbationReviews\Pages;

use App\Filament\Resources\PerformanceProbationReviews\PerformanceProbationReviewResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceProbationReview extends EditRecord
{
    protected static string $resource = PerformanceProbationReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
