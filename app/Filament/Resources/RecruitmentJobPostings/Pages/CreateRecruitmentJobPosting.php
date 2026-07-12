<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentJobPostings\Pages;

use App\Filament\Resources\RecruitmentJobPostings\RecruitmentJobPostingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentJobPosting extends CreateRecord
{
    protected static string $resource = RecruitmentJobPostingResource::class;
}
