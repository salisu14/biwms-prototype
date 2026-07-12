<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentJobPostings\Pages;

use App\Filament\Resources\RecruitmentJobPostings\RecruitmentJobPostingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentJobPosting extends ViewRecord
{
    protected static string $resource = RecruitmentJobPostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
