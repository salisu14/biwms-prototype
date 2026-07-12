<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentPreEmploymentChecks\Pages;

use App\Filament\Resources\RecruitmentPreEmploymentChecks\RecruitmentPreEmploymentCheckResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentPreEmploymentChecks extends ListRecords
{
    protected static string $resource = RecruitmentPreEmploymentCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
