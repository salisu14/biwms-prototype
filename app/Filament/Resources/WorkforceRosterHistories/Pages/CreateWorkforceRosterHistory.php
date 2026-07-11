<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterHistories\Pages;

use App\Filament\Resources\WorkforceRosterHistories\WorkforceRosterHistoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceRosterHistory extends CreateRecord
{
    protected static string $resource = WorkforceRosterHistoryResource::class;
}
