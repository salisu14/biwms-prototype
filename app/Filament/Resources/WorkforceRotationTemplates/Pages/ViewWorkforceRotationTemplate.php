<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationTemplates\Pages;

use App\Filament\Resources\WorkforceRotationTemplates\WorkforceRotationTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkforceRotationTemplate extends ViewRecord
{
    protected static string $resource = WorkforceRotationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
