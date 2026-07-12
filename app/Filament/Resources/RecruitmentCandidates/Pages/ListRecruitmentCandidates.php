<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentCandidates\Pages;

use App\Filament\Resources\RecruitmentCandidates\RecruitmentCandidateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentCandidates extends ListRecords
{
    protected static string $resource = RecruitmentCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
