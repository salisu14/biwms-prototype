<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentHistories\Pages;

use App\Filament\Resources\RecruitmentHistories\RecruitmentHistoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentHistory extends CreateRecord
{
    protected static string $resource = RecruitmentHistoryResource::class;
}
