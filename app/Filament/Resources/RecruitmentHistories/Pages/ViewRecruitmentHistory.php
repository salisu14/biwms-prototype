<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentHistories\Pages;

use App\Filament\Resources\RecruitmentHistories\RecruitmentHistoryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentHistory extends ViewRecord
{
    protected static string $resource = RecruitmentHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
