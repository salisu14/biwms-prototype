<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisals\Pages;

use App\Filament\Resources\PerformanceAppraisals\PerformanceAppraisalResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceAppraisal extends CreateRecord
{
    protected static string $resource = PerformanceAppraisalResource::class;
}
