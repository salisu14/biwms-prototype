<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentJobPostings\Pages;

use App\Filament\Resources\RecruitmentJobPostings\RecruitmentJobPostingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentJobPosting extends EditRecord
{
    protected static string $resource = RecruitmentJobPostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
