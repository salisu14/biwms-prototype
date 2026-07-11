<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalCycles\Pages;

use App\Filament\Resources\PerformanceAppraisalCycles\PerformanceAppraisalCycleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceAppraisalCycle extends CreateRecord
{
    protected static string $resource = PerformanceAppraisalCycleResource::class;
}
