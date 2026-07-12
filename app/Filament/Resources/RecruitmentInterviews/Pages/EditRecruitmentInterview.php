<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentInterviews\Pages;

use App\Filament\Resources\RecruitmentInterviews\RecruitmentInterviewResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentInterview extends EditRecord
{
    protected static string $resource = RecruitmentInterviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
