<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationTemplates\Pages;

use App\Filament\Resources\WorkforceRotationTemplates\WorkforceRotationTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkforceRotationTemplates extends ListRecords
{
    protected static string $resource = WorkforceRotationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
