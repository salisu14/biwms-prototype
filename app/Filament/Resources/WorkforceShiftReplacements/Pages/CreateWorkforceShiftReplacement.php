<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftReplacements\Pages;

use App\Filament\Resources\WorkforceShiftReplacements\WorkforceShiftReplacementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceShiftReplacement extends CreateRecord
{
    protected static string $resource = WorkforceShiftReplacementResource::class;
}
