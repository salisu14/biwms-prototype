<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplicationScreenings\Pages;

use App\Filament\Resources\RecruitmentApplicationScreenings\RecruitmentApplicationScreeningResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentApplicationScreenings extends ListRecords
{
    protected static string $resource = RecruitmentApplicationScreeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
