<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentPreEmploymentChecks\Pages;

use App\Filament\Resources\RecruitmentPreEmploymentChecks\RecruitmentPreEmploymentCheckResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentPreEmploymentCheck extends CreateRecord
{
    protected static string $resource = RecruitmentPreEmploymentCheckResource::class;
}
