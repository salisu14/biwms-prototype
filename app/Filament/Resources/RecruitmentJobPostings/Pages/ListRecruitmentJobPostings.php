<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentJobPostings\Pages;

use App\Filament\Resources\RecruitmentJobPostings\RecruitmentJobPostingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentJobPostings extends ListRecords
{
    protected static string $resource = RecruitmentJobPostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
