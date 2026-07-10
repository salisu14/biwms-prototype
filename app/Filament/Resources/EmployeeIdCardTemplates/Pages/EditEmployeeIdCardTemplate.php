<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardTemplates\Pages;

use App\Filament\Resources\EmployeeIdCardTemplates\EmployeeIdCardTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeIdCardTemplate extends EditRecord
{
    protected static string $resource = EmployeeIdCardTemplateResource::class;
}
