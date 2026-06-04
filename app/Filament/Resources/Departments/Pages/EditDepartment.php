<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartment extends EditRecord
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
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
