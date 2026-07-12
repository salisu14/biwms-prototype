<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviews\Pages;

use App\Filament\Resources\RecruitmentInterviews\RecruitmentInterviewResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentInterview extends CreateRecord
{
    protected static string $resource = RecruitmentInterviewResource::class;
}
