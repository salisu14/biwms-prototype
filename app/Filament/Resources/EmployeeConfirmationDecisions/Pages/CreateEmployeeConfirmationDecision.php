<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeConfirmationDecisions\Pages;

use App\Filament\Resources\EmployeeConfirmationDecisions\EmployeeConfirmationDecisionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeConfirmationDecision extends CreateRecord
{
    protected static string $resource = EmployeeConfirmationDecisionResource::class;
}
