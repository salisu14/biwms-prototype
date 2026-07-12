<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeConfirmationDecisions\Pages;

use App\Filament\Resources\EmployeeConfirmationDecisions\EmployeeConfirmationDecisionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeConfirmationDecision extends EditRecord
{
    protected static string $resource = EmployeeConfirmationDecisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
