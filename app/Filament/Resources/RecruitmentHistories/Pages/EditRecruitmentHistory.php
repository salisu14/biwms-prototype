<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecruitmentHistories\Pages;

use App\Filament\Resources\RecruitmentHistories\RecruitmentHistoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentHistory extends EditRecord
{
    protected static string $resource = RecruitmentHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
