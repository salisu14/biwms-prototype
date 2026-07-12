<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplications\Pages;

use App\Filament\Resources\RecruitmentApplications\RecruitmentApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentApplications extends ListRecords
{
    protected static string $resource = RecruitmentApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
