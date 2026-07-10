<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardTemplates\Pages;

use App\Filament\Resources\EmployeeIdCardTemplates\EmployeeIdCardTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeIdCardTemplate extends CreateRecord
{
    protected static string $resource = EmployeeIdCardTemplateResource::class;
}
