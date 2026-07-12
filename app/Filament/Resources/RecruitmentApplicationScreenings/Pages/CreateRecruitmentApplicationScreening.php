<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplicationScreenings\Pages;

use App\Filament\Resources\RecruitmentApplicationScreenings\RecruitmentApplicationScreeningResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentApplicationScreening extends CreateRecord
{
    protected static string $resource = RecruitmentApplicationScreeningResource::class;
}
