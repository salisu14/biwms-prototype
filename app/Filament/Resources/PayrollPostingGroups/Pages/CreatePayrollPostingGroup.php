<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollPostingGroups\Pages;

use App\Filament\Resources\PayrollPostingGroups\PayrollPostingGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollPostingGroup extends CreateRecord
{
    protected static string $resource = PayrollPostingGroupResource::class;
}
