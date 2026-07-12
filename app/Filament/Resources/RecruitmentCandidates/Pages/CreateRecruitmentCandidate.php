<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentCandidates\Pages;

use App\Filament\Resources\RecruitmentCandidates\RecruitmentCandidateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentCandidate extends CreateRecord
{
    protected static string $resource = RecruitmentCandidateResource::class;
}
