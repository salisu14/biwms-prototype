<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentCandidates\Pages;

use App\Filament\Resources\RecruitmentCandidates\RecruitmentCandidateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentCandidate extends EditRecord
{
    protected static string $resource = RecruitmentCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
