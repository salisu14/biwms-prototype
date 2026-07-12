<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentCandidates\Pages;

use App\Filament\Resources\RecruitmentCandidates\RecruitmentCandidateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentCandidate extends ViewRecord
{
    protected static string $resource = RecruitmentCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
