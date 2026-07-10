<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardTemplates\Pages;

use App\Filament\Resources\EmployeeIdCardTemplates\EmployeeIdCardTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeIdCardTemplates extends ListRecords
{
    protected static string $resource = EmployeeIdCardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
