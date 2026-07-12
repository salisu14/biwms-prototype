<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeConfirmationDecisions\Pages;

use App\Filament\Resources\EmployeeConfirmationDecisions\EmployeeConfirmationDecisionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeConfirmationDecisions extends ListRecords
{
    protected static string $resource = EmployeeConfirmationDecisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
