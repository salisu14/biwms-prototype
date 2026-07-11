<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalHistories\Pages;

use App\Filament\Resources\PerformanceAppraisalHistories\PerformanceAppraisalHistoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceAppraisalHistory extends CreateRecord
{
    protected static string $resource = PerformanceAppraisalHistoryResource::class;
}
