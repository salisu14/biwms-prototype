<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplications\Pages;

use App\Filament\Resources\RecruitmentApplications\RecruitmentApplicationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentApplication extends CreateRecord
{
    protected static string $resource = RecruitmentApplicationResource::class;
}
