<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviews\Pages;

use App\Filament\Resources\RecruitmentInterviews\RecruitmentInterviewResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentInterview extends ViewRecord
{
    protected static string $resource = RecruitmentInterviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
