<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewPanels\Pages;

use App\Filament\Resources\RecruitmentInterviewPanels\RecruitmentInterviewPanelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentInterviewPanels extends ListRecords
{
    protected static string $resource = RecruitmentInterviewPanelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
