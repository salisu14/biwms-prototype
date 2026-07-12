<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentPreEmploymentChecks\Pages;

use App\Filament\Resources\RecruitmentPreEmploymentChecks\RecruitmentPreEmploymentCheckResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentPreEmploymentCheck extends ViewRecord
{
    protected static string $resource = RecruitmentPreEmploymentCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
