<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterPeriods\Pages;

use App\Filament\Resources\WorkforceRosterPeriods\WorkforceRosterPeriodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceRosterPeriod extends CreateRecord
{
    protected static string $resource = WorkforceRosterPeriodResource::class;
}
