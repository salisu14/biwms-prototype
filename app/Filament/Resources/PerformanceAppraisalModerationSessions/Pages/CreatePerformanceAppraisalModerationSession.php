<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalModerationSessions\Pages;

use App\Filament\Resources\PerformanceAppraisalModerationSessions\PerformanceAppraisalModerationSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceAppraisalModerationSession extends CreateRecord
{
    protected static string $resource = PerformanceAppraisalModerationSessionResource::class;
}
