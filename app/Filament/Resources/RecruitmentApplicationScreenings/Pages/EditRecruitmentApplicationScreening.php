<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentApplicationScreenings\Pages;

use App\Filament\Resources\RecruitmentApplicationScreenings\RecruitmentApplicationScreeningResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentApplicationScreening extends EditRecord
{
    protected static string $resource = RecruitmentApplicationScreeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
