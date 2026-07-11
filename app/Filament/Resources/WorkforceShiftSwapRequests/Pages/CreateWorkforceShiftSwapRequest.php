<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftSwapRequests\Pages;

use App\Filament\Resources\WorkforceShiftSwapRequests\WorkforceShiftSwapRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceShiftSwapRequest extends CreateRecord
{
    protected static string $resource = WorkforceShiftSwapRequestResource::class;
}
