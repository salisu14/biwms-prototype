<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentPreEmploymentChecks\Pages;

use App\Filament\Resources\RecruitmentPreEmploymentChecks\RecruitmentPreEmploymentCheckResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentPreEmploymentCheck extends EditRecord
{
    protected static string $resource = RecruitmentPreEmploymentCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
