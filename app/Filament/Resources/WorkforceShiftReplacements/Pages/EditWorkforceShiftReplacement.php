<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftReplacements\Pages;

use App\Filament\Resources\WorkforceShiftReplacements\WorkforceShiftReplacementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceShiftReplacement extends EditRecord
{
    protected static string $resource = WorkforceShiftReplacementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
