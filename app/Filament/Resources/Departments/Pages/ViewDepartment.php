<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDepartment extends ViewRecord
{
    protected static string $resource = DepartmentResource::class;

    public function getHeading(): string
    {
        return 'Department Code '.($this->record->department_code ?? '—')
            .' • Scope '.($this->record->name ?? '—')
            .' • Attribute '.($this->record->manager?->employee_number
                ? "{$this->record->manager->employee_number} - {$this->record->manager->first_name} {$this->record->manager->last_name}"
                : 'Unassigned');
    }

    public function getSubheading(): string
    {
        return 'Code '.($this->record->department_code ?? '—')
            .' • Scope '.($this->record->name ?? '—')
            .' • Attribute '.($this->record->manager?->employee_number
                ? "{$this->record->manager->employee_number} - {$this->record->manager->first_name} {$this->record->manager->last_name}"
                : 'Unassigned');
    }

    public function getBreadcrumb(): string
    {
        return ($this->record->department_code ?? '—')
            .' - '.($this->record->name ?? '—');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
