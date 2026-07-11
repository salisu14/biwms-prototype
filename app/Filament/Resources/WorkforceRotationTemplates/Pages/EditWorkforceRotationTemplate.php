<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationTemplates\Pages;

use App\Filament\Resources\WorkforceRotationTemplates\WorkforceRotationTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceRotationTemplate extends EditRecord
{
    protected static string $resource = WorkforceRotationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
