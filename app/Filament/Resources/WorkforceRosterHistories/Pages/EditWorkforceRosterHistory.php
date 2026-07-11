<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterHistories\Pages;

use App\Filament\Resources\WorkforceRosterHistories\WorkforceRosterHistoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceRosterHistory extends EditRecord
{
    protected static string $resource = WorkforceRosterHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
