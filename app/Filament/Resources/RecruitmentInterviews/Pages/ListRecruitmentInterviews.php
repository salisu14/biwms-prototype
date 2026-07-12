<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviews\Pages;

use App\Filament\Resources\RecruitmentInterviews\RecruitmentInterviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentInterviews extends ListRecords
{
    protected static string $resource = RecruitmentInterviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
