<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeConfirmationDecisions\Pages;

use App\Filament\Resources\EmployeeConfirmationDecisions\EmployeeConfirmationDecisionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeConfirmationDecision extends ViewRecord
{
    protected static string $resource = EmployeeConfirmationDecisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
