<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviewPanels\Pages;

use App\Filament\Resources\RecruitmentInterviewPanels\RecruitmentInterviewPanelResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentInterviewPanel extends EditRecord
{
    protected static string $resource = RecruitmentInterviewPanelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
