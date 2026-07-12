<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplicationScreenings\Pages;

use App\Filament\Resources\RecruitmentApplicationScreenings\RecruitmentApplicationScreeningResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentApplicationScreening extends ViewRecord
{
    protected static string $resource = RecruitmentApplicationScreeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
