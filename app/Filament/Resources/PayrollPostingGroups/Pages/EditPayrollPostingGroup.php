<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollPostingGroups\Pages;

use App\Filament\Resources\PayrollPostingGroups\PayrollPostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayrollPostingGroup extends EditRecord
{
    protected static string $resource = PayrollPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
