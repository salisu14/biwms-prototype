<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollPostingGroups\Pages;

use App\Filament\Resources\PayrollPostingGroups\PayrollPostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollPostingGroups extends ListRecords
{
    protected static string $resource = PayrollPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
