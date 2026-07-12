<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewPanels\Pages;

use App\Filament\Resources\RecruitmentInterviewPanels\RecruitmentInterviewPanelResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentInterviewPanel extends ViewRecord
{
    protected static string $resource = RecruitmentInterviewPanelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
