<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationTemplates\Pages;

use App\Filament\Resources\WorkforceRotationTemplates\WorkforceRotationTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceRotationTemplate extends CreateRecord
{
    protected static string $resource = WorkforceRotationTemplateResource::class;
}
