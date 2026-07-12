<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentSelectionReviews\Pages;

use App\Filament\Resources\RecruitmentSelectionReviews\RecruitmentSelectionReviewResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentSelectionReview extends EditRecord
{
    protected static string $resource = RecruitmentSelectionReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
