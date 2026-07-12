<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentSelectionReviews\Pages;

use App\Filament\Resources\RecruitmentSelectionReviews\RecruitmentSelectionReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentSelectionReviews extends ListRecords
{
    protected static string $resource = RecruitmentSelectionReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
